# Authentication & Signing

Songfacts API v5 uses header-based HMAC-SHA256 request signing. Every request is authenticated with your `client_id`, signed with your shared secret, and protected against replay by a timestamp and a nonce.

This is a **server-to-server** contract. The shared secret must never reach a browser or mobile client.

- [Credentials](#credentials)
- [Required headers](#required-headers)
- [The canonical string](#the-canonical-string)
- [Body hash](#body-hash)
- [Signature](#signature)
- [Replay protection](#replay-protection)
- [Verification order](#verification-order)
- [Troubleshooting bad_signature](#troubleshooting-bad_signature)

---

## Credentials

| | |
| --- | --- |
| `client_id` | Lowercase slug-style identifier derived from your company name, e.g. `rolling-stone`. Sent in the clear as `X-Client-Id`. |
| Shared secret | Opaque random value (32 random bytes, issued as `base64url` text). Used only as the HMAC key — never transmitted. |

Both are issued by Songfacts. One secret per client. If a secret is rotated, the old one stops working immediately, so plan credential updates as a deploy.

A secret is valid while the client account is active and inside its access window. Secrets themselves do not carry an expiry date.

**Use the secret string exactly as issued.** Pass it as UTF-8 text to your HMAC function; do not base64-decode it first.

## Required headers

Send all of these on every request:

| Header | Rules |
| --- | --- |
| `Content-Type` | `application/json` — JSON only, no form-encoded fallback |
| `X-Client-Id` | Your lowercase slug client ID |
| `X-Timestamp` | Unix epoch time in **whole seconds** (no milliseconds, no ISO-8601) |
| `X-Nonce` | ASCII only, length **16–128**, characters `A-Z` `a-z` `0-9` `_` `-`. Fresh on every request. |
| `X-Signature` | Exactly **64 lowercase hex** characters — an HMAC-SHA256 digest |

Missing any of the four `X-` headers returns `auth` / `missing_auth_headers`.

A practical nonce: 24 random bytes, base64url-encoded, no padding — 32 characters, all inside the allowed character set.

```python
secrets.token_urlsafe(24)                                    # Python
crypto.randomBytes(24).toString("base64url")                 # Node.js
rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=')  # PHP
openssl rand -hex 16                                         # shell
```

## The canonical string

The signed message is exactly five lines joined with `\n`:

```text
METHOD
PATH
TIMESTAMP
NONCE
BODY_HASH
```

Example:

```text
POST
/v5/songfacts
1718064000
abc123_nonce_token
1ade4d63aae82dcb1fe757a3ab942e06446631052ac01b795e38f2d3d38c14ec
```

Rules:

- **`METHOD`** is uppercase. Always `POST` for identified operations.
- **`PATH`** is the URL path only — no scheme, no host, no query string.
  - Correct: `/v5/songfacts`, `/v5/artistfacts`
  - Wrong: `https://api.songfacts.com/v5/songfacts`, `/v5/songfacts.json`, `/v5/songfacts/`
  - There is no trailing-slash normalization. Sign and send the no-trailing-slash form.
- **`TIMESTAMP`** is the same value you send as `X-Timestamp`.
- **`NONCE`** is the same value you send as `X-Nonce`.
- **`BODY_HASH`** is described below.
- The string is UTF-8 text joined with **`\n` only**. No `\r\n`. No trailing newline.

## Body hash

```text
BODY_HASH = lowercase_hex( SHA-256( exact raw request body bytes ) )
```

The hash must be taken over the bytes that actually go on the wire. If your HTTP client re-serializes, reorders keys, or pretty-prints the JSON after you hash it, the signature will not verify.

The reliable pattern: **serialize once to a string or byte buffer, hash that buffer, sign, then send that same buffer as the raw body.** Every sample in [examples/](../examples/README.md) does this.

If the body is empty, use the SHA-256 of the empty string:

```text
e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
```

## Signature

```text
algorithm : HMAC-SHA256
message   : the canonical string (UTF-8)
key       : your shared secret (as issued, UTF-8)
output    : lowercase hex, exactly 64 characters
```

Send the result as `X-Signature`. Uppercase hex and base64 are both rejected.

## Replay protection

| | |
| --- | --- |
| Timestamp window | ±300 seconds (5 minutes) around server time |
| Nonce reuse | Rejected for the duration of the acceptance window |

Outside the window: `auth` / `expired_timestamp`. Reused nonce: `auth` / `replayed_nonce`.

Implications for your client:

- **Keep server clocks in sync.** NTP drift is a leading cause of `expired_timestamp` in production.
- **Generate a new nonce per request.** Never cache or reuse a signed header set, and never retry a failed request with the same nonce — re-sign it.
- Nonce uniqueness is enforced globally during the active window, so use a cryptographically random generator rather than a counter.

Only the hash of a nonce is stored, and only briefly.

## Verification order

Knowing the order helps you read failures:

1. Required auth headers present
2. `X-Client-Id` format valid
3. `X-Timestamp` format and acceptance window
4. `X-Nonce` format
5. Nonce replay check
6. Client lookup by `X-Client-Id` → `unknown_client` if absent
7. Client active state → `inactive_client`
8. Secret loaded
9. Canonical string rebuilt
10. Signature verified (constant-time comparison) → `bad_signature`

Then, after authentication succeeds: endpoint access check (`endpoint_not_allowed`), then request body validation (`invalid_fields` and friends).

So a body-validation error means your signing is already correct — and an auth error means the body was never examined.

## Troubleshooting `bad_signature`

In order of how often each one is the culprit:

1. **You signed a different body than you sent.** Pretty-printing, key reordering, an added trailing newline, or a framework that re-encodes JSON on send. Hash the exact outgoing buffer.
2. **You signed the full URL instead of the path.** Line 2 is `/v5/songfacts`, nothing more.
3. **Wrong signature encoding.** It must be lowercase hex, 64 chars — not uppercase, not base64.
4. **Line separator problems.** `\r\n` instead of `\n`, or a trailing newline on the fifth line.
5. **Wrong key.** Base64-decoding the secret first, trimming it, or picking up a stale value from the environment.
6. **Mismatched timestamp or nonce.** The values inside the canonical string must be byte-identical to the header values.

Debugging tip: print your canonical string with the newlines made visible, plus the body hash and the exact body bytes, and compare them side by side.

```python
print(repr(canonical))
print(len(signature), signature)
```

If everything looks right and it still fails, confirm you're calling the host assigned to your credentials — trial, staging and production clients are separate accounts with separate secrets.

## Next reads

- [Quick Start](quick-start.md)
- [Error Handling](errors.md) — every error type and code
- [Code samples](../examples/README.md) — signing implemented in cURL, Node.js, Python and PHP
