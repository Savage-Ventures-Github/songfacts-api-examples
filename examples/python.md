# Python Sample

A complete Songfacts API v5 client in Python. **Standard library only** — no `requests`, no SDK. Python 3.8+.

- [The client](#the-client)
- [The four core flows](#the-four-core-flows)
- [Reading the result](#reading-the-result)
- [Paging through facts](#paging-through-facts)
- [Verifying your signing](#verifying-your-signing)

Environment: `BASE_URL`, `CLIENT_ID`, `CLIENT_SECRET` — see [setup](README.md#setup).

---

## The client

`songfacts_client.py`

```python
"""Minimal Songfacts API v5 client — standard library only."""

import hashlib
import hmac
import json
import os
import secrets
import time
import urllib.error
import urllib.request

BASE_URL = os.environ.get("BASE_URL", "https://api.songfacts.com").rstrip("/")
CLIENT_ID = os.environ["CLIENT_ID"]
CLIENT_SECRET = os.environ["CLIENT_SECRET"]


def call(path, payload, timeout=10):
    """POST a signed JSON request to `path` (e.g. "/v5/songfacts").

    Returns the parsed response body. The API answers HTTP 200 for both
    success and failure, so inspect meta.status / meta.matched to branch.
    """
    # Serialize exactly once. This is the buffer we hash, sign, AND send --
    # if the bytes on the wire differ from the bytes we hashed, the signature
    # will not verify.
    body = json.dumps(payload, separators=(",", ":")).encode("utf-8")

    timestamp = str(int(time.time()))          # whole seconds
    nonce = secrets.token_urlsafe(24)          # 32 chars from [A-Za-z0-9_-]
    body_hash = hashlib.sha256(body).hexdigest()

    # Five lines, joined with \n: METHOD, PATH, TIMESTAMP, NONCE, BODY_HASH.
    canonical = "\n".join(["POST", path, timestamp, nonce, body_hash])

    signature = hmac.new(
        CLIENT_SECRET.encode("utf-8"),         # secret as issued -- do not base64-decode
        canonical.encode("utf-8"),
        hashlib.sha256,
    ).hexdigest()                              # lowercase hex, 64 chars

    request = urllib.request.Request(
        BASE_URL + path,
        data=body,
        method="POST",
        headers={
            "Content-Type": "application/json",
            "X-Client-Id": CLIENT_ID,
            "X-Timestamp": timestamp,
            "X-Nonce": nonce,
            "X-Signature": signature,
        },
    )

    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return json.loads(response.read().decode("utf-8"))
    except urllib.error.HTTPError as exc:
        # Non-200 is a transport or infrastructure problem, not an API verdict.
        detail = exc.read().decode("utf-8", "replace")
        raise RuntimeError("HTTP %s: %s" % (exc.code, detail)) from exc


def songfacts(**payload):
    return call("/v5/songfacts", payload)


def artistfacts(**payload):
    return call("/v5/artistfacts", payload)
```

## The four core flows

`run_examples.py`

```python
import json

from songfacts_client import artistfacts, songfacts

examples = [
    ("songfacts by id",     lambda: songfacts(id=933)),
    ("songfacts by name",   lambda: songfacts(artist="U2", title="One")),
    ("artistfacts by id",   lambda: artistfacts(id=24)),
    ("artistfacts by name", lambda: artistfacts(artist="Metallica")),
]

for label, run in examples:
    print("=" * 60)
    print(label)
    print("=" * 60)
    print(json.dumps(run(), indent=2)[:1500])
```

```bash
python run_examples.py
```

## Reading the result

Branch on `meta.status` first, then `meta.matched` — never on the HTTP status.

```python
def facts_for_track(artist, title):
    """Return (facts, attribution_url) for a track, or (None, None) if unmatched."""
    result = songfacts(artist=artist, title=title, limit=5, debug=True)
    meta = result.get("meta", {})

    if meta.get("status") == "fail":
        error = meta["error"]
        if error["type"] == "internal":
            raise RetryableError(error["code"])          # retry with backoff
        if error["type"] in ("auth", "access"):
            raise ConfigurationError(error["code"])      # alert; do not retry
        raise BadRequestError(error)                     # bug in the call -- log error["fields"]

    if not meta.get("matched"):
        # Not an error. Log the diagnostics and collapse the module.
        debug = result.get("debug", {})
        log.info(
            "no songfacts match",
            extra={
                "normalized_input": debug.get("normalized_input"),
                "match": debug.get("match"),
                "best_candidate": result.get("best_candidate"),
            },
        )
        return None, None

    song = result["song"]

    # facts.items is keyed by fact ID; values are HTML; canonical order is
    # newest-first (descending fact ID). Sort explicitly rather than trusting
    # JSON key order.
    facts = [
        (int(fact_id), html)
        for fact_id, html in song["facts"]["items"].items()
    ]
    facts.sort(key=lambda pair: pair[0], reverse=True)

    return facts, song["url"]
```

Notes:

- **`facts.items` values are HTML** — they contain inline `<i>` and `<b>`. Render as HTML; there's no plain-text twin field. For text-to-speech, strip tags.
- **Optional fields are omitted, not `null`.** Use `.get()` / key checks for `youtube`, `lyrics`, `album`, `charts` and friends.
- **Cache `song["id"]`** against your own catalog ID, then use `songfacts(id=...)` from then on — exact and immune to matcher variance.

## Paging through facts

```python
def all_facts(song_id, page_size=50):
    """Yield every fact for a song, newest first."""
    offset = 0
    while True:
        result = call("/v5/songfacts", {"id": song_id, "limit": page_size, "offset": offset})
        song = result["song"]
        page = song.get("facts", {}).get("items", {})   # omitted when offset is past the end

        for fact_id in sorted(page, key=int, reverse=True):
            yield int(fact_id), page[fact_id]

        pagination = song["pagination"]
        if pagination["not_displayed"] == 0 or pagination["displayed"] == 0:
            return
        offset += pagination["displayed"]
```

Resolve once by name, then page by `id`. To fetch everything in a single call use `{"showall": True}` — with no `limit` or `offset` alongside it.

## Verifying your signing

Signing is deterministic, so you can validate it with no network call:

```python
import hashlib
import hmac

secret = "test-secret"
body = b'{"artist":"U2","title":"One"}'

body_hash = hashlib.sha256(body).hexdigest()
canonical = "\n".join(["POST", "/v5/songfacts", "1718064000", "abc123_nonce_token", body_hash])
signature = hmac.new(secret.encode(), canonical.encode(), hashlib.sha256).hexdigest()

assert body_hash == "1ade4d63aae82dcb1fe757a3ab942e06446631052ac01b795e38f2d3d38c14ec"
assert signature == "9ffdbed7075e2bf9ab81e1ae045f98f9d6750c11bd41d1532d712d9cd18a1773"
print("signing OK")
```

If those match, your canonical string, body hash, encoding and HMAC are all correct — any remaining failure is credentials or clock skew. If they don't, print `repr(canonical)` and compare it against the [canonical string spec](../docs/authentication.md#the-canonical-string).

## Next reads

- [Authentication & Signing](../docs/authentication.md)
- [Error Handling](../docs/errors.md)
- [`/v5/songfacts`](../docs/endpoints/songfacts.md) · [`/v5/artistfacts`](../docs/endpoints/artistfacts.md)
