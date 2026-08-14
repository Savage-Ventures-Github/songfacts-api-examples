# Quick Start

The minimum steps to make your first successful Songfacts API v5 call.

- [1. Get your credentials](#1-get-your-credentials)
- [2. Build a JSON request body](#2-build-a-json-request-body)
- [3. Build the canonical string](#3-build-the-canonical-string)
- [4. Sign it](#4-sign-it)
- [5. Send the request](#5-send-the-request)
- [6. Read the response](#6-read-the-response)
- [7. Turn on diagnostics](#7-turn-on-diagnostics)

---

## 1. Get your credentials

You need three things:

| | |
| --- | --- |
| `BASE_URL` | `https://api.songfacts.com`, or the staging/dev host assigned to your integration |
| `CLIENT_ID` | A lowercase slug-style identifier, e.g. `rolling-stone` |
| `CLIENT_SECRET` | An opaque random shared secret, issued as `base64url` text |

Credentials are provisioned by Songfacts per client — see [Get Access](../README.md#get-access-to-songfacts-api). Access is also granted per endpoint, so confirm which endpoints your client is enabled for.

Use the secret **exactly as issued**, as a text string. Do not base64-decode it before using it as the HMAC key.

> Keep the secret server-side. Requests are signed, which means anything holding the secret can call the API as you. Never ship it in a browser bundle, mobile app, or public repository.

## 2. Build a JSON request body

Requests are `POST` with `Content-Type: application/json`. Field names are `snake_case`.

**Songfacts by ID**

```json
{ "id": 1 }
```

**Songfacts by artist and title** — both fields are required; title-only lookup is not supported.

```json
{ "artist": "U2", "title": "One" }
```

**Artistfacts by ID**

```json
{ "id": 42 }
```

**Artistfacts by artist**

```json
{ "artist": "Metallica" }
```

Optional on all four: `limit`, `offset`, `showall`, `debug`. See [Requests & Responses](requests-and-responses.md).

## 3. Build the canonical string

Five lines, joined with `\n` (newline only, no carriage returns, no trailing newline):

```text
POST
/v5/songfacts
1718064000
abc123_nonce_token
1ade4d63aae82dcb1fe757a3ab942e06446631052ac01b795e38f2d3d38c14ec
```

| Line | Value |
| --- | --- |
| 1 | HTTP method, uppercase — always `POST` |
| 2 | URL path only — no scheme, host, query string, `.json` suffix, or trailing slash |
| 3 | Unix epoch time in whole seconds |
| 4 | Your nonce — ASCII, 16–128 chars, from `A-Z a-z 0-9 _ -`, fresh on every request |
| 5 | Lowercase hex SHA-256 of the **exact raw request body bytes** you will send |

## 4. Sign it

```text
algorithm : HMAC-SHA256
message   : the canonical string (UTF-8)
key       : your shared secret
output    : lowercase hex, exactly 64 characters
```

Full rules and the most common signing mistakes: [Authentication & Signing](authentication.md).

## 5. Send the request

```bash
curl -sS -X POST https://api.songfacts.com/v5/songfacts \
  -H 'Content-Type: application/json' \
  -H 'X-Client-Id: your-client-id' \
  -H 'X-Timestamp: 1718064000' \
  -H 'X-Nonce: abc123_nonce_token' \
  -H 'X-Signature: 4f1c...64 hex chars...' \
  --data-binary '{"artist":"U2","title":"One"}'
```

Required headers: `Content-Type`, `X-Client-Id`, `X-Timestamp`, `X-Nonce`, `X-Signature`.

Working, signed versions of this in four languages: [examples/](../examples/README.md).

## 6. Read the response

**The HTTP status is always `200`.** The JSON body tells you what happened.

```text
meta.status = "ok"    → the request was accepted and processed
meta.status = "fail"  → the request was rejected; see meta.error
meta.matched = true   → a record was found; it's under `song` or `artist`
meta.matched = false  → nothing cleared the match threshold. This is NOT an error.
```

A match returns the entity, its pagination state, and its facts nested inside it:

```json
{
  "meta": { "status": "ok", "matched": true },
  "song": {
    "id": 1,
    "title": "Can't Buy Me Love",
    "slug": "cant-buy-me-love",
    "url": "https://www.songfacts.com/facts/the-beatles/cant-buy-me-love",
    "artist": {
      "id": 197,
      "name": "The Beatles",
      "slug": "the-beatles",
      "url": "https://www.songfacts.com/facts/the-beatles"
    },
    "youtube": {
      "youtubeid": "x45cV6mA6pY",
      "url": "https://www.youtube.com/watch?v=x45cV6mA6pY"
    },
    "pagination": {
      "limit": 10,
      "offset": 0,
      "displayed": 10,
      "total": 11,
      "not_displayed": 1,
      "limit_clamped": true
    },
    "facts": {
      "items": {
        "66793": "Jim Carrey and Zooey Deschanel perform part of this song in the 2008 movie <i>Yes Man</i>."
      }
    }
  }
}
```

Three things to internalize:

1. **`facts.items` is keyed by fact ID** (a string key holding an integer row ID), and values are **HTML**. Order is newest to oldest, i.e. descending fact ID. Render the HTML; don't expect a plain-text twin field.
2. **Pagination lives inside the matched object**, and defaults to `limit: 10`. `total` is the full available fact count, so `not_displayed` tells you whether to ask for more.
3. **A failure looks completely different** — only `meta`, with an error object:

   ```json
   { "meta": { "status": "fail", "error": { "type": "auth", "code": "bad_signature" } } }
   ```

Branch on `meta.status` first, then `meta.matched`. See [Error Handling](errors.md).

## 7. Turn on diagnostics

Add `"debug": true` to any request body and the response gains a root-level `debug` object explaining how the match was reached:

```json
{
  "debug": {
    "endpoint": "songfacts",
    "lookup_mode": "id",
    "normalized_input": { "id": 1, "limit": 10, "offset": 0 },
    "cache_hit": false,
    "candidate_count": 1,
    "match": { "match_method": "id_exact", "match_score": 100 }
  }
}
```

`debug` is invaluable while integrating — especially `normalized_input`, which shows what the API actually searched on, and `debug.match.match_score` against `match_threshold_applied`. It's omitted unless you ask for it, and omitted entirely on failure responses.

Treat `debug` as diagnostic output, not a versioned contract. Log it; don't branch production behavior on specific fuzzy method names or weighting fields. See [Matching & Scores](matching.md).

## Next reads

- [Authentication & Signing](authentication.md) — get signing right the first time
- [Requests & Responses](requests-and-responses.md) — every field, every validation rule
- [`/v5/songfacts`](endpoints/songfacts.md) · [`/v5/artistfacts`](endpoints/artistfacts.md)
- [Error Handling](errors.md)
- [Use Cases](use-cases.md) — what to build with it
