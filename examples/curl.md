# cURL / bash Sample

Sign and send Songfacts API v5 requests from a shell. Useful for a first smoke test, for reproducing a failure by hand, and for confirming your credentials work before you write any code.

Requires `bash`, `curl` and `openssl`.

- [The shared helper](#the-shared-helper)
- [The four core flows](#the-four-core-flows)
- [One-liner without the helper](#one-liner-without-the-helper)
- [Reading the result](#reading-the-result)
- [Verifying your signing](#verifying-your-signing)

Environment: `BASE_URL`, `CLIENT_ID`, `CLIENT_SECRET` — see [setup](README.md#setup).

---

## The shared helper

`sf_call.sh`

```bash
#!/usr/bin/env bash
# Sign and send a Songfacts API v5 request.
# Usage: ./sf_call.sh /v5/songfacts '{"artist":"U2","title":"One"}'
set -euo pipefail

API_PATH="${1:?usage: sf_call.sh <path> <json-body>}"   # e.g. /v5/songfacts
BODY="${2:?usage: sf_call.sh <path> <json-body>}"       # exact JSON to send

: "${BASE_URL:=https://api.songfacts.com}"
: "${CLIENT_ID:?CLIENT_ID must be set}"
: "${CLIENT_SECRET:?CLIENT_SECRET must be set}"

TIMESTAMP="$(date +%s)"                                 # whole seconds
NONCE="$(openssl rand -hex 16)"                         # 32 chars from [0-9a-f]

# Lowercase hex SHA-256 of the exact body bytes. printf '%s' avoids the
# trailing newline that echo would add -- which would change the hash.
BODY_HASH="$(printf '%s' "$BODY" | openssl dgst -sha256 | awk '{print $NF}')"

# Five lines joined with \n: METHOD, PATH, TIMESTAMP, NONCE, BODY_HASH.
# Note there is no trailing newline after the fifth line.
CANONICAL="$(printf '%s\n%s\n%s\n%s\n%s' "POST" "$API_PATH" "$TIMESTAMP" "$NONCE" "$BODY_HASH")"

# HMAC-SHA256 with the secret as issued, lowercase hex output.
SIGNATURE="$(printf '%s' "$CANONICAL" \
  | openssl dgst -sha256 -mac HMAC -macopt "key:$CLIENT_SECRET" \
  | awk '{print $NF}')"

curl -sS -X POST "${BASE_URL%/}${API_PATH}" \
  -H 'Content-Type: application/json' \
  -H "X-Client-Id: ${CLIENT_ID}" \
  -H "X-Timestamp: ${TIMESTAMP}" \
  -H "X-Nonce: ${NONCE}" \
  -H "X-Signature: ${SIGNATURE}" \
  --data-binary "$BODY"
```

```bash
chmod +x sf_call.sh
```

> Two details that matter: **`printf '%s'` rather than `echo`** (echo appends a newline, which changes the body hash and breaks the signature), and **`--data-binary` rather than `-d`** (`-d` strips newlines from the payload, so the bytes sent would no longer be the bytes signed).
>
> `$API_PATH` is used instead of `$PATH` on purpose — overwriting `PATH` would break the shell.

## The four core flows

```bash
# songfacts by id
./sf_call.sh /v5/songfacts '{"id":933}'

# songfacts by artist + title
./sf_call.sh /v5/songfacts '{"artist":"U2","title":"One"}'

# artistfacts by id
./sf_call.sh /v5/artistfacts '{"id":24}'

# artistfacts by artist
./sf_call.sh /v5/artistfacts '{"artist":"Metallica"}'
```

With diagnostics and paging, piped through `jq`:

```bash
./sf_call.sh /v5/songfacts '{"artist":"U2","title":"One","limit":3,"debug":true}' | jq

./sf_call.sh /v5/songfacts '{"id":933,"limit":10,"offset":10}' | jq '.song.pagination'

./sf_call.sh /v5/artistfacts '{"id":24,"showall":true}' | jq '.artist.facts.items | length'
```

One-off run without exporting first:

```bash
BASE_URL="https://api.songfacts.com" \
CLIENT_ID="your-client-id" \
CLIENT_SECRET="your-shared-secret" \
./sf_call.sh /v5/songfacts '{"id":933}'
```

## One-liner without the helper

Copy-pasteable, everything inline:

```bash
BODY='{"artist":"U2","title":"One"}'
API_PATH='/v5/songfacts'
TS="$(date +%s)"
NONCE="$(openssl rand -hex 16)"
BH="$(printf '%s' "$BODY" | openssl dgst -sha256 | awk '{print $NF}')"
SIG="$(printf '%s\n%s\n%s\n%s\n%s' POST "$API_PATH" "$TS" "$NONCE" "$BH" \
  | openssl dgst -sha256 -mac HMAC -macopt "key:$CLIENT_SECRET" | awk '{print $NF}')"

curl -sS -X POST "https://api.songfacts.com${API_PATH}" \
  -H 'Content-Type: application/json' \
  -H "X-Client-Id: $CLIENT_ID" \
  -H "X-Timestamp: $TS" \
  -H "X-Nonce: $NONCE" \
  -H "X-Signature: $SIG" \
  --data-binary "$BODY" | jq
```

## Reading the result

**The HTTP status is always `200`.** Read `meta`:

```bash
# did it match?
./sf_call.sh /v5/songfacts '{"artist":"U2","title":"One"}' | jq '.meta'
# { "status": "ok", "matched": true }

# just the newest fact
./sf_call.sh /v5/songfacts '{"id":933,"limit":1}' \
  | jq -r '.song.facts.items | to_entries[0].value'

# facts newest-first, as id + text
./sf_call.sh /v5/songfacts '{"id":933}' \
  | jq -r '.song.facts.items | to_entries | sort_by(.key | tonumber) | reverse | .[] | "\(.key)\t\(.value)"'

# how many facts exist in total
./sf_call.sh /v5/songfacts '{"id":933}' | jq '.song.pagination'

# why did a lookup behave that way?
./sf_call.sh /v5/songfacts '{"artist":"utwo","title":"on","debug":true}' | jq '.debug'
```

Fact values are **HTML** — expect inline `<i>` and `<b>` in the strings.

Failures look completely different from matches:

```bash
CLIENT_SECRET=wrong ./sf_call.sh /v5/songfacts '{"id":933}' | jq
# { "meta": { "status": "fail", "error": { "type": "auth", "code": "bad_signature" } } }

./sf_call.sh /v5/songfacts '{"title":"One"}' | jq '.meta.error'
# { "type": "request", "code": "invalid_fields", "fields": { "artist": "required" }, ... }
```

See [Error Handling](../docs/errors.md).

## Verifying your signing

Signing is deterministic, so you can check the shell pipeline against known values with no network call:

```bash
SECRET='test-secret'
BODY='{"artist":"U2","title":"One"}'

BH="$(printf '%s' "$BODY" | openssl dgst -sha256 | awk '{print $NF}')"
SIG="$(printf '%s\n%s\n%s\n%s\n%s' POST /v5/songfacts 1718064000 abc123_nonce_token "$BH" \
  | openssl dgst -sha256 -mac HMAC -macopt "key:$SECRET" | awk '{print $NF}')"

echo "$BH"
# 1ade4d63aae82dcb1fe757a3ab942e06446631052ac01b795e38f2d3d38c14ec
echo "$SIG"
# 9ffdbed7075e2bf9ab81e1ae045f98f9d6750c11bd41d1532d712d9cd18a1773
```

If those two values match, your canonical string, body hash, encoding and HMAC are all correct — any remaining failure is credentials or clock skew.

Debugging tip: `printf '%s' "$CANONICAL" | od -c | head` shows exactly where your line breaks and trailing bytes are.

## Next reads

- [Authentication & Signing](../docs/authentication.md)
- [Error Handling](../docs/errors.md)
- [`/v5/songfacts`](../docs/endpoints/songfacts.md) · [`/v5/artistfacts`](../docs/endpoints/artistfacts.md)
