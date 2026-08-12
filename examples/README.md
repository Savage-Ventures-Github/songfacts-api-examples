# Code Samples

Signed, runnable examples for Songfacts API v5 in four languages. Each one implements the same thing: build the canonical string, sign it, send a JSON `POST`, and inspect the result.

| Language | File | Requires |
| --- | --- | --- |
| cURL / bash | [curl.md](curl.md) | `bash`, `curl`, `openssl` |
| JavaScript | [javascript.md](javascript.md) | Node.js 18+ (built-in `fetch` and `node:crypto`) |
| Python | [python.md](python.md) | Python 3.8+ (standard library only) |
| PHP | [php.md](php.md) | PHP 7.4+ with cURL |

No SDK is required — signing is about twenty lines in any language.

## Setup

Every sample reads the same three environment variables:

```bash
export BASE_URL="https://api.songfacts.com"
export CLIENT_ID="your-client-id"
export CLIENT_SECRET="your-shared-secret"
```

PowerShell:

```powershell
$env:BASE_URL = 'https://api.songfacts.com'
$env:CLIENT_ID = 'your-client-id'
$env:CLIENT_SECRET = 'your-shared-secret'
```

Set `BASE_URL` to the host assigned to your integration — production, or a staging/dev host provided for your account. Credentials come from Songfacts; see [Get Access](../README.md#get-access-to-songfacts-api).

> **Never commit the secret, and never ship it to a client device.** Anything holding it can call the API as you. If you prefer not to use environment variables, load it from your secrets manager — just don't paste it into a file you'll push.

## The four core flows

Each sample covers all four:

| Flow | Endpoint | Body |
| --- | --- | --- |
| Songfacts by ID | `/v5/songfacts` | `{"id": 933}` |
| Songfacts by name | `/v5/songfacts` | `{"artist": "U2", "title": "One"}` |
| Artistfacts by ID | `/v5/artistfacts` | `{"id": 24}` |
| Artistfacts by name | `/v5/artistfacts` | `{"artist": "Metallica"}` |

## The signing recipe

Identical in every language:

```text
body      = the exact JSON bytes you will send
body_hash = lowercase_hex(sha256(body))
canonical = "POST" + "\n" + path + "\n" + timestamp + "\n" + nonce + "\n" + body_hash
signature = lowercase_hex(hmac_sha256(key = client_secret, message = canonical))
```

Then send:

```text
Content-Type: application/json
X-Client-Id:  <client_id>
X-Timestamp:  <timestamp>
X-Nonce:      <nonce>
X-Signature:  <signature>
```

Three rules that account for nearly every `bad_signature`:

1. **Serialize the body once.** Hash that exact buffer, sign it, and send that same buffer. Every sample below does this deliberately — don't let an HTTP client re-serialize your JSON after you've signed it.
2. **Sign the path only** — `/v5/songfacts`, not the full URL, no query string, no trailing slash.
3. **Fresh nonce and timestamp on every request**, including retries.

Full rules: [Authentication & Signing](../docs/authentication.md).

## Verifying your implementation

Signing is deterministic, so you can check your code without making a network call. With:

```text
secret    = test-secret
method    = POST
path      = /v5/songfacts
timestamp = 1718064000
nonce     = abc123_nonce_token
body      = {"artist":"U2","title":"One"}
```

you must get:

```text
body_hash = 1ade4d63aae82dcb1fe757a3ab942e06446631052ac01b795e38f2d3d38c14ec
signature = 9ffdbed7075e2bf9ab81e1ae045f98f9d6750c11bd41d1532d712d9cd18a1773
```

> These reference digests were generated from the samples in this folder. Compute them with your own implementation using the inputs above — if both values match, your canonical string, body hash, encoding and HMAC are all correct, and any remaining failure is credentials or clock. If they don't match, print `repr(canonical)` and compare it line by line against the [canonical string spec](../docs/authentication.md#the-canonical-string).

## What to expect back

| Outcome | Body |
| --- | --- |
| Match | `meta.status = "ok"`, `meta.matched = true`, plus `song` or `artist` |
| No match | `meta.status = "ok"`, `meta.matched = false` — a successful call, not an error |
| Auth failure | `meta.status = "fail"`, `meta.error.type = "auth"` |
| Validation failure | `meta.status = "fail"`, `meta.error.code = "invalid_fields"` |

**The HTTP status is always `200`.** Branch on `meta.status`, then `meta.matched`. See [Error Handling](../docs/errors.md).

Add `"debug": true` to any body while integrating — the root `debug` object shows the normalized input the matcher actually used, the method it chose, and the score against the threshold.
