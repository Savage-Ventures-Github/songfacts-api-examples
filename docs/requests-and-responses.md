# Requests & Responses

The shared conventions behind both endpoints: what you may send, how it's validated, and the shape of what comes back.

- [Request rules](#request-rules)
- [Lookup fields](#lookup-fields)
- [Pagination fields](#pagination-fields)
- [Diagnostics field](#diagnostics-field)
- [Numeric and boolean coercion](#numeric-and-boolean-coercion)
- [Response envelope](#response-envelope)
- [The matched object](#the-matched-object)
- [The facts payload](#the-facts-payload)
- [Pagination in the response](#pagination-in-the-response)
- [The debug object](#the-debug-object)

---

## Request rules

| | |
| --- | --- |
| Method | `POST`. Anything else returns `request` / `invalid_method`. |
| Content type | `application/json`. Anything else returns `request` / `invalid_content_type`. |
| Body | A single JSON object. Malformed JSON returns `request` / `invalid_json`. |
| Field naming | `snake_case`. |
| Unknown fields | **Rejected.** Any field not in the accepted set fails the request with `invalid_fields`. Don't pass through your own tracking keys. |

## Lookup fields

| Field | Type | Endpoint | Notes |
| --- | --- | --- | --- |
| `id` | integer | both | Public ID. Maps internally to the song ID for `songfacts` and the artist ID for `artistfacts`. Exact lookup only. |
| `artist` | string | both | Artist name. |
| `title` | string | `songfacts` only | Song title. Sending it to `artistfacts` is invalid. |

Rules:

- **Songfacts by name requires both `artist` and `title`.** Title-only lookup is not supported.
- **Artistfacts by name requires `artist`.**
- **ID and name lookup cannot be mixed.** `id` together with `artist` or `title` is rejected — see [conflict codes](#conflict-codes).
- **ID lookup does not fall back to name matching.** If the ID doesn't exist, you get a no-match, not a guess.
- `id` is numeric in both request and response. `id = 0` is invalid.
- Empty or whitespace-only `artist` / `title` count as missing (`required`).

## Pagination fields

| Field | Type | Default | Notes |
| --- | --- | --- | --- |
| `limit` | integer | `10` | Maximum `50`. Values above the max are silently clamped and reported via `limit_clamped`. `limit = 0` is invalid. |
| `offset` | integer | `0` | `0` is valid. Negative is invalid. An out-of-range offset returns a valid empty page. |
| `showall` | boolean | — | Returns the full fact set. May be restricted by access policy (`showall_not_allowed`). Cannot be combined with `limit` or `offset`. |

Pagination is on by default. Requesting without `limit` applies the default of `10`, and that counts as an internal clamp — you'll see `limit_clamped: true`.

## Diagnostics field

| Field | Type | Notes |
| --- | --- | --- |
| `debug` | boolean | Adds a root-level `debug` object to processed responses. Available to all clients. Omitted from failure responses. |

## Numeric and boolean coercion

The API is deliberately strict, so integration bugs surface immediately instead of becoming silent behavior differences.

**Numbers** (`id`, `limit`, `offset`) accept a native JSON number or a clean integer string. Rejected:

| Value | Why |
| --- | --- |
| `" 12"` | whitespace padding |
| `"012"` | leading zero |
| `12.0`, `"12.5"` | not an integer |
| `"12abc"` | mixed content |
| `-1` | negative |

**Booleans** (`showall`, `debug`) must be real JSON booleans. `"true"`, `"false"`, `1` and `0` are all rejected.

```json
{ "id": 933, "limit": 25, "debug": true }     // valid
{ "id": "933", "limit": "25", "debug": true }  // valid — clean integer strings
{ "id": "0933", "debug": "true" }              // invalid on both fields
```

### Validation responses

All field problems in a request are reported **together** in a single response — you don't fix them one round-trip at a time. `invalid_fields` responses always include `expected_fields`.

```json
{
  "meta": {
    "status": "fail",
    "error": {
      "type": "request",
      "code": "invalid_fields",
      "fields": {
        "title": "required",
        "limit": "invalid"
      },
      "expected_fields": ["artist", "title", "id", "limit", "offset", "showall", "debug"]
    }
  }
}
```

### Conflict codes

When fields are individually valid but can't be combined, the code names the conflict:

| Field | Code |
| --- | --- |
| `id` | `not_allowed_with_name_lookup` |
| `artist` | `not_allowed_with_id_lookup` |
| `title` | `not_allowed_with_id_lookup` |
| `showall` | `not_allowed_with_pagination` |
| `limit` | `not_allowed_with_showall` |
| `offset` | `not_allowed_with_showall` |

## Response envelope

**The HTTP status is always `200`.** The body determines the outcome.

```text
{
  "meta": { ... }            always present
  "song" | "artist": { ... } present when meta.matched is true
  "best_candidate": { ... }  optional, on name-based no-match
  "debug": { ... }           present only when debug: true was requested
}
```

`meta` on a processed request carries exactly two fields:

| Field | Values |
| --- | --- |
| `status` | `"ok"` — request accepted and processed. `"fail"` — request rejected. |
| `matched` | `true` / `false`. Present on processed requests. |

On a **failure**, `meta` contains only `status` and `error`, and root `debug` is omitted:

```json
{ "meta": { "status": "fail", "error": { "type": "auth", "code": "expired_timestamp" } } }
```

**A no-match is not a failure.** It's `status: "ok"` with `matched: false`. See [Error Handling](errors.md) for the complete failure model.

### No-match and `best_candidate`

Name-based lookups that find something close but below the threshold may return a minimal top-level `best_candidate`, so you can decide for yourself whether to use it, log it for catalog reconciliation, or show a "did you mean" affordance.

```json
{
  "meta": { "status": "ok", "matched": false },
  "best_candidate": {
    "id": 933,
    "artist": "U2",
    "title": "One",
    "match_score": 62,
    "match_method": "fuzzy_fulltext_reranked"
  }
}
```

Artist candidates carry `id`, `artist`, `match_score` and `match_method`. **ID no-match never includes `best_candidate`** — an ID either exists or it doesn't.

## The matched object

Matched data lives under an endpoint-specific top-level key — `song` or `artist` — never under a generic `match`. Request state stays in `meta`; it is not mixed into the matched object.

Inside the matched object you get, together:

- **Identity** — `id`, `title` / `name`, `slug`
- **Canonical URL** — the public Songfacts page for the entity, suitable for attribution links
- **Enrichment** — nested artist, YouTube, lyrics metadata and other denormalized fields, where available
- **`pagination`** — the applied paging state
- **`facts`** — the payload you came for

Optional enrichments are **omitted when unavailable** rather than returned as `null`, so check for key presence. Field-by-field references: [`/v5/songfacts`](endpoints/songfacts.md) · [`/v5/artistfacts`](endpoints/artistfacts.md).

## The facts payload

```json
"facts": {
  "items": {
    "190815": "U2 recorded a new version of \"One\" for their 2023 album <b>Songs Of Surrender</b>.",
    "14682": "The song was written during the <i>Achtung Baby</i> sessions in Berlin."
  }
}
```

| | |
| --- | --- |
| Structure | An object keyed by fact ID. The key is the integer fact row ID, serialized as a JSON string. |
| Values | **HTML.** Facts contain inline markup such as `<i>` and `<b>`, and are meant to be rendered as HTML. There is no duplicate plain-text field. |
| Ordering | Newest to oldest — descending fact ID. Clients cannot change the sort. |
| Content | Full facts, not previews or truncated teasers. |
| Absence | `facts` is present on successful matches, with one exception: when `offset` is past the end of the set, you get a valid response with `displayed: 0` and **no** `facts` key. |

Two integration notes:

- **JSON objects don't guarantee key order in every language.** If you need the canonical newest-first order, sort the keys as integers descending after parsing — don't rely on your JSON library preserving insertion order.
- **Fact IDs are stable identifiers.** Use them for deduplication, "show me a fact I haven't shown this user yet" rotation, caching, and editorial flagging.

## Pagination in the response

`pagination` sits inside the matched object and is always present on a successful match.

```json
"pagination": {
  "limit": 10,
  "offset": 0,
  "displayed": 10,
  "total": 23,
  "not_displayed": 13,
  "limit_clamped": true
}
```

| Field | Meaning |
| --- | --- |
| `limit` | The applied limit |
| `offset` | The applied offset, always returned — including the default `0` |
| `displayed` | Number of facts in this response |
| `total` | Full available fact count before pagination |
| `not_displayed` | Remaining facts not in this response. Always present, even when `0`. |
| `showall` | Included only when `showall` was sent in the request |
| `limit_clamped` | Included only when the server clamped the limit — including when you omitted `limit` and the default `10` was applied |

Behavior notes:

- With `showall: true`, pagination is still returned and reflects the full returned set.
- An out-of-range `offset` is a **successful match** with `displayed: 0` and no `facts` key — not an error.

## The debug object

Requested with `"debug": true`, returned at the response root:

```json
"debug": {
  "endpoint": "songfacts",
  "lookup_mode": "name",
  "normalized_input": { "artist": "utwo", "title": "on", "limit": 10, "offset": 0 },
  "cache_hit": false,
  "candidate_count": 1,
  "match": { "match_method": "fuzzy_short_title_artist_pool", "match_score": 91, "match_threshold_applied": 80 }
}
```

| Field | Meaning |
| --- | --- |
| `endpoint` | `songfacts` or `artistfacts` |
| `lookup_mode` | `id` or `name` |
| `normalized_input` | The internally normalized values actually used for lookup — never your raw input. The single most useful field when a lookup surprises you. |
| `cache_hit` | Boolean. Shared match caching is currently suspended, so this remains `false` in the live runtime. |
| `candidate_count` | Candidates considered. For ID lookups: `1` when found, `0` when not. Omitted when there were no candidates at all, while the rest of `debug` is kept. |
| `match` | Matcher diagnostics — see [Matching & Scores](matching.md) |

Rules:

- `debug` never duplicates public response fields.
- `debug` is omitted unless requested, and always omitted on failure responses.
- It is **diagnostic output, not a versioned contract.** Log it freely; don't build production logic on specific fuzzy method names or score-weighting fields.

## Next reads

- [Matching & Scores](matching.md)
- [Error Handling](errors.md)
- [`/v5/songfacts`](endpoints/songfacts.md) · [`/v5/artistfacts`](endpoints/artistfacts.md)
