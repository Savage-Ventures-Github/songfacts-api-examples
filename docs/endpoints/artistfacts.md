# `POST /v5/artistfacts`

Resolve an artist and return their Artistfacts — curated background on the singers and bands behind the music: origins, lineups, personal stories, influences, career milestones and lasting legacy.

```text
POST https://api.songfacts.com/v5/artistfacts
Content-Type: application/json
```

- [Request](#request)
- [Response: successful match](#response-successful-match)
- [Artist object reference](#artist-object-reference)
- [Response: no match](#response-no-match)
- [Response: failure](#response-failure)
- [Paging through facts](#paging-through-facts)
- [Samples](#samples)

---

## Request

### By ID

```json
{ "id": 24 }
```

Exact lookup. No fallback to name matching. `id = 0` is invalid.

If you already have a song from [`/v5/songfacts`](songfacts.md), its nested `song.artist.id` is exactly this value — use it instead of a second name match.

### By artist

```json
{ "artist": "Metallica" }
```

### Fields

| Field | Type | Required | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | integer | one of | — | Public artist ID. Mutually exclusive with `artist`. |
| `artist` | string | one of | — | Artist name |
| `limit` | integer | no | `10` | Max `50`; clamped silently |
| `offset` | integer | no | `0` | Out-of-range returns a valid empty page |
| `showall` | boolean | no | — | Full fact set. Not combinable with `limit` / `offset`. May be restricted per client. |
| `debug` | boolean | no | — | Adds root `debug` diagnostics |

**`title` is not valid on this endpoint.** Sending it fails the request.

## Response: successful match

```json
{
  "meta": {
    "status": "ok",
    "matched": true
  },
  "artist": {
    "id": 24,
    "name": "Metallica",
    "slug": "metallica",
    "url": "https://www.songfacts.com/facts/metallica",
    "pagination": {
      "limit": 10,
      "offset": 0,
      "displayed": 10,
      "total": 27,
      "not_displayed": 17,
      "limit_clamped": true
    },
    "facts": {
      "items": {
        "14682": "On their 2003-2004 tour, Metallica started mixing up their setlists, a process they refined over the years. Lars Ulrich started gathering data and curating the setlists carefully so they wouldn't repeat too many songs in the same city. This wasn't just for the fans: It keeps the band on their toes and makes sure they don't go on autopilot when they're on stage."
      }
    }
  },
  "debug": {
    "endpoint": "artistfacts",
    "lookup_mode": "name",
    "normalized_input": {
      "artist": "metallica",
      "limit": 10,
      "offset": 0
    },
    "cache_hit": false,
    "candidate_count": 1,
    "match": {
      "match_method": "normalized_exact",
      "match_score": 100,
      "match_threshold_applied": 100,
      "match_artist_score": 100,
      "match_artist_score_contribution": 90,
      "match_engine_score": 0,
      "match_engine_score_contribution": 0,
      "match_score_reference_total": 90
    }
  }
}
```

Root `debug` appears only when `"debug": true` was sent.

## Artist object reference

`artistfacts` returns **artist-only data**. There is no related-song context in the payload — if you need songs, call [`/v5/songfacts`](songfacts.md).

Optional fields are omitted when unavailable rather than returned as `null`.

| Field | Type | Notes |
| --- | --- | --- |
| `id` | integer | Public artist ID. Stable — cache it. |
| `name` | string | Canonical Songfacts artist name |
| `slug` | string | URL slug |
| `url` | string | Canonical Songfacts artist page. Use it for attribution links. |
| `dates_of_existence` | string | Optional — active years for a band or act |
| `members` | — | Optional lineup data |
| `pagination` | object | Always present on a successful match — [field reference](../requests-and-responses.md#pagination-in-the-response) |
| `facts.items` | object | Keyed by fact ID; values are **HTML**; ordered newest-first. Omitted when `offset` is out of range. |

> Note: the artist identity is `name`, not a flat `artist` string. (Earlier draft documentation showed a flat field; the live shape is `name` / `slug` / `url`.) The `best_candidate` object is the one place a public field is still called `artist` — see below.

## Response: no match

```json
{
  "meta": { "status": "ok", "matched": false },
  "best_candidate": {
    "id": 24,
    "artist": "Metallica",
    "match_score": 71,
    "match_method": "fuzzy_artist_fulltext_reranked"
  }
}
```

- `status` stays `"ok"` — no-match is not an error.
- Name-based no-match may include `best_candidate` with `id`, `artist`, `match_score` and `match_method` when a strong-but-below-threshold candidate exists.
- **ID no-match never includes `best_candidate`.**
- With `"debug": true`, `debug.match` retains `match_method`, `match_score` and `match_threshold_applied` so you can see how far off the lookup was.

Match methods for this endpoint: `id_exact`, `normalized_exact`, `fuzzy_artist_fulltext_reranked` (threshold `80`). See [Matching & Scores](../matching.md).

## Response: failure

```json
{ "meta": { "status": "fail", "error": { "type": "auth", "code": "bad_signature" } } }
```

Common cases specific to this endpoint:

| Situation | Response |
| --- | --- |
| `title` sent | `invalid_fields` — `title` is not accepted here |
| `id` plus `artist` | `invalid_fields` with `artist: "not_allowed_with_id_lookup"` |
| Empty `artist` string | `invalid_fields` with `artist: "required"` |
| Client not granted `artistfacts` | `access` / `endpoint_not_allowed` |

Full reference: [Error Handling](../errors.md).

## Paging through facts

Identical model to `songfacts`. Established artists carry long fact sets, and `total` / `not_displayed` tell you what remains.

**Page 1** — `{"artist": "Metallica"}` → `limit: 10, offset: 0, displayed: 10, total: 27, not_displayed: 17`

**Page 2** — `{"id": 24, "limit": 10, "offset": 10}` → `displayed: 10, not_displayed: 7`

**Larger page** — `{"id": 24, "limit": 50}` → `displayed: 27, not_displayed: 0` (clamped to the available count)

**Everything at once** — `{"id": 24, "showall": true}`, with no `limit` or `offset` alongside it, subject to `showall_not_allowed`.

Resolve once by name, then page by `id`.

## Samples

Signed, runnable versions of both request modes:

- [cURL / bash](../../examples/curl.md)
- [JavaScript (Node.js)](../../examples/javascript.md)
- [Python](../../examples/python.md)
- [PHP](../../examples/php.md)

Product patterns built on this endpoint: [Use Cases](../use-cases.md).
