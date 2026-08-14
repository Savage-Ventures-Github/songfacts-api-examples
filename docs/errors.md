# Error Handling

Songfacts API v5 separates **transport success** from **API outcome**, and separates both from **"we found nothing."** Getting this model right up front saves a lot of defensive code later.

- [The three outcomes](#the-three-outcomes)
- [The error object](#the-error-object)
- [Error reference](#error-reference)
- [No-match is not an error](#no-match-is-not-an-error)
- [Recommended client logic](#recommended-client-logic)
- [Retry guidance](#retry-guidance)

---

## The three outcomes

**The HTTP status is always `200`.** Do not branch on it; a non-200 means a network or infrastructure problem, not an API decision.

| Outcome | Body |
| --- | --- |
| **Match** | `meta.status = "ok"`, `meta.matched = true`, plus `song` or `artist` |
| **No match** | `meta.status = "ok"`, `meta.matched = false` — a successful call with an empty result |
| **Failure** | `meta.status = "fail"`, plus `meta.error` |

```json
{ "meta": { "status": "ok", "matched": true }, "song": { "...": "..." } }
```

```json
{ "meta": { "status": "ok", "matched": false } }
```

```json
{ "meta": { "status": "fail", "error": { "type": "auth", "code": "bad_signature" } } }
```

On a failure, `meta` contains **only** `status` and `error` — no `matched`, no `match`, and root `debug` is omitted even if you asked for it.

## The error object

Compact by design:

| Field | Presence |
| --- | --- |
| `type` | Always — the broad category: `auth`, `access`, `request`, `internal` |
| `code` | Always — the specific machine-readable reason |
| `fields` | On field-related request errors. Keyed by field name, with short string codes. |
| `expected_fields` | Always on `invalid_fields` |

There is intentionally **no `message` field**. Codes are the contract; human-readable copy belongs in your application, where you control tone and locale.

```json
{
  "meta": {
    "status": "fail",
    "error": {
      "type": "request",
      "code": "invalid_fields",
      "fields": { "title": "required", "id": "not_allowed_with_name_lookup" },
      "expected_fields": ["artist", "title", "id", "limit", "offset", "showall", "debug"]
    }
  }
}
```

All field problems in a request are reported together, so one round-trip surfaces everything.

## Error reference

### `type: "auth"` — credentials and signing

| Code | Meaning | Fix |
| --- | --- | --- |
| `missing_auth_headers` | One or more of `X-Client-Id`, `X-Timestamp`, `X-Nonce`, `X-Signature` absent | Send all four on every request |
| `unknown_client` | No client matches `X-Client-Id` | Check the slug; confirm you're on the host your credentials were issued for |
| `bad_signature` | HMAC verification failed | See [troubleshooting](authentication.md#troubleshooting-bad_signature) — usually a body/signature mismatch |
| `expired_timestamp` | `X-Timestamp` outside the ±300s window | Sync server clocks (NTP); don't reuse pre-signed requests |
| `replayed_nonce` | Nonce already used in the active window | Generate a fresh nonce per request, including on retries |

### `type: "access"` — provisioning and policy

| Code | Meaning | Fix |
| --- | --- | --- |
| `inactive_client` | Client account is not active, or is past its access window | Contact Songfacts |
| `endpoint_not_allowed` | Your client isn't granted this endpoint | Endpoints are assigned per client; request the grant |
| `showall_not_allowed` | `showall: true` isn't permitted for your client | Use `limit` / `offset` pagination |
| `quota_exceeded` | Metered allowance consumed (applies to trial and limited keys) | Contact Songfacts to raise or extend the allowance |

### `type: "request"` — malformed input

| Code | Meaning | Fix |
| --- | --- | --- |
| `invalid_method` | Not a `POST`. Response includes the expected method. | Use `POST` |
| `invalid_content_type` | Wrong content type. Response includes the expected type. | Send `Content-Type: application/json` |
| `invalid_json` | Body isn't parseable JSON | Check serialization; ensure the raw body is valid |
| `invalid_fields` | One or more fields missing, invalid, unknown, or in conflict | Read `fields` and `expected_fields` |

Field codes inside `invalid_fields`:

| Code | Meaning |
| --- | --- |
| `required` | Missing, empty, or whitespace-only |
| `invalid` | Present but fails type or format rules |
| `not_allowed_with_id_lookup` | `artist` / `title` sent alongside `id` |
| `not_allowed_with_name_lookup` | `id` sent alongside `artist` / `title` |
| `not_allowed_with_pagination` | `showall` sent with `limit` or `offset` |
| `not_allowed_with_showall` | `limit` / `offset` sent with `showall` |

Unknown fields also fail under `invalid_fields` — the accepted set is closed. Full rules: [Requests & Responses](requests-and-responses.md#numeric-and-boolean-coercion).

### `type: "internal"` — server side

| Code | Meaning | Fix |
| --- | --- | --- |
| `internal_error` | Unexpected server error | Retry with backoff; report if persistent |
| `database_error` | Backend datastore failure | Retry with backoff |
| `cache_error` | Cache layer failure | Retry with backoff |

Internal errors are the only category worth automatic retry — everything else is deterministic and will fail identically until you change something.

## No-match is not an error

There is no error code for "not found." A lookup that finds nothing is a completed request:

```json
{
  "meta": { "status": "ok", "matched": false },
  "debug": {
    "endpoint": "songfacts",
    "lookup_mode": "name",
    "normalized_input": { "artist": "U2", "title": "zzzzzzzzzz", "limit": 10, "offset": 0 },
    "cache_hit": false,
    "candidate_count": 0,
    "match": {
      "match_method": "fuzzy_title_fulltext_reranked",
      "match_score": 0,
      "match_threshold_applied": 70
    }
  }
}
```

Two related non-errors worth knowing:

- **Out-of-range `offset`** is a successful *match* with `displayed: 0` and no `facts` key.
- **`best_candidate`** on a name-based no-match tells you something close existed but didn't clear the threshold. Use it for reconciliation or a "did you mean" prompt.

On metered plans, note that **authenticated no-matches count against quota** while auth failures and request errors do not.

## Recommended client logic

```python
result = call("/v5/songfacts", {"artist": artist, "title": title})

meta = result.get("meta", {})

if meta.get("status") == "fail":
    err = meta["error"]
    if err["type"] == "internal":
        raise RetryableError(err["code"])        # retry with backoff
    if err["type"] in ("auth", "access"):
        raise ConfigurationError(err["code"])    # alert; do not retry
    raise BadRequestError(err)                   # bug in your call; log fields

if not meta.get("matched"):
    log_unmatched(artist, title, result.get("best_candidate"))
    return None                                  # render the surface without facts

song = result["song"]
render(song["facts"]["items"], attribution_url=song["url"])
```

Three habits that pay off:

1. **Branch on `meta.status`, then `meta.matched`.** Never on the HTTP status.
2. **Make no-match a first-class UI state.** Every music catalog has long-tail tracks with no Songfacts record. Design the surface to collapse gracefully rather than showing an error.
3. **Alert on `auth` and `access` errors.** They mean credentials, clocks or provisioning changed — not a bad user input — and they will not fix themselves.

## Retry guidance

| Situation | Retry? |
| --- | --- |
| `internal_error`, `database_error`, `cache_error` | Yes — exponential backoff |
| Network timeout, non-200 HTTP status | Yes — exponential backoff |
| `auth` / `access` codes | No — alert instead |
| `request` codes | No — fix the call |
| `matched: false` | No |

**Always re-sign a retry.** A retry must carry a fresh timestamp and a fresh nonce; replaying the original headers returns `replayed_nonce`, and a stale timestamp returns `expired_timestamp`.

## Next reads

- [Authentication & Signing](authentication.md)
- [Requests & Responses](requests-and-responses.md)
- [Matching & Scores](matching.md)
