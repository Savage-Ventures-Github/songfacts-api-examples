# `POST /v5/songfacts`

Resolve a song and return its Songfacts® — the stories, background and trivia behind that specific track: lyrical meaning, who wrote it, how it was recorded, notable chart positions, and the tidbits in between.

```text
POST https://api.songfacts.com/v5/songfacts
Content-Type: application/json
```

- [Request](#request)
- [Response: successful match](#response-successful-match)
- [Song object reference](#song-object-reference)
- [Response: no match](#response-no-match)
- [Response: failure](#response-failure)
- [Paging through facts](#paging-through-facts)
- [Samples](#samples)

---

## Request

### By ID

```json
{ "id": 933 }
```

Exact lookup. No fallback to name matching. `id = 0` is invalid.

### By artist and title

```json
{ "artist": "U2", "title": "One" }
```

**Both fields are required.** Title-only lookup is not supported — a title without an artist is too ambiguous to resolve to one record.

`id` may not be combined with `artist` or `title`.

### Fields

| Field | Type | Required | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | integer | one of | — | Public song ID. Mutually exclusive with `artist` / `title`. |
| `artist` | string | one of | — | Required with `title` for name lookup |
| `title` | string | one of | — | Required with `artist` for name lookup |
| `limit` | integer | no | `10` | Max `50`; clamped silently |
| `offset` | integer | no | `0` | Out-of-range returns a valid empty page |
| `showall` | boolean | no | — | Full fact set. Not combinable with `limit` / `offset`. May be restricted per client. |
| `debug` | boolean | no | — | Adds root `debug` diagnostics |

Type rules (clean integer strings, real booleans, unknown fields rejected): [Requests & Responses](../requests-and-responses.md#numeric-and-boolean-coercion).

## Response: successful match

```json
{
  "meta": {
    "status": "ok",
    "matched": true
  },
  "song": {
    "id": 933,
    "title": "One",
    "slug": "one",
    "url": "https://www.songfacts.com/facts/u2/one",
    "artist": {
      "id": 61,
      "name": "U2",
      "slug": "u2",
      "url": "https://www.songfacts.com/facts/u2"
    },
    "youtube": {
      "youtubeid": "ftjEcrrf7r0",
      "url": "https://www.youtube.com/watch?v=ftjEcrrf7r0"
    },
    "has_lyrics": true,
    "lyrics": {
      "available": true,
      "writers": "Bono, Adam Clayton, The Edge, Larry Mullen Jr.",
      "preview": "Is it getting better, or do you feel the same? Will it make it easier on you now, you got someone to blame..."
    },
    "pagination": {
      "limit": 10,
      "offset": 0,
      "displayed": 10,
      "total": 23,
      "not_displayed": 13,
      "limit_clamped": true
    },
    "facts": {
      "items": {
        "190815": "U2 recorded a new version of \"One\" for their 2023 album <b>Songs Of Surrender</b>. It debuted at the Super Bowl that year as part of the Walter Payton Man Of The Year presentation. Later that night, U2 announced their Las Vegas residency at the MSG Sphere in a commercial that aired just after the game."
      }
    }
  },
  "debug": {
    "endpoint": "songfacts",
    "lookup_mode": "name",
    "normalized_input": {
      "artist": "utwo",
      "title": "on",
      "limit": 10,
      "offset": 0
    },
    "cache_hit": false,
    "candidate_count": 1,
    "match": {
      "match_method": "fuzzy_short_title_artist_pool",
      "match_score": 91,
      "match_threshold_applied": 80,
      "match_artist_score": 100,
      "match_title_score": 92,
      "match_artist_score_contribution": 45,
      "match_title_score_contribution": 46,
      "match_engine_score": 0,
      "match_engine_score_contribution": 0,
      "match_score_reference_total": 91
    }
  }
}
```

Root `debug` appears only when `"debug": true` was sent.

## Song object reference

Optional fields are **omitted when unavailable** rather than returned as `null` — check for key presence.

### Identity

| Field | Type | Notes |
| --- | --- | --- |
| `id` | integer | Public song ID. Stable — cache it against your own catalog ID. |
| `title` | string | Canonical Songfacts title |
| `slug` | string | URL slug |
| `url` | string | Canonical Songfacts page for the song. Use it for attribution links. |

### Nested artist

| Field | Type |
| --- | --- |
| `artist.id` | integer |
| `artist.name` | string |
| `artist.slug` | string |
| `artist.url` | string |

`artist.id` feeds straight into [`/v5/artistfacts`](artistfacts.md) as an `id` lookup — that's the intended way to link a song surface to an artist surface without a second name match.

### Media and enrichment

| Field | Type | Notes |
| --- | --- | --- |
| `youtube.youtubeid` | string | Optional |
| `youtube.url` | string | Optional. Watch URL for the official/canonical video. |
| `has_lyrics` | boolean | Whether lyrics data exists for the song |
| `lyrics.available` | boolean | Whether lyrics content is available to your client |
| `lyrics.writers` | string | Credited songwriters |
| `lyrics.ownership` | string | Optional rights/ownership note |
| `lyrics.preview` | string | Optional short lyric excerpt |
| `album` | string | Optional |
| `release_year` | integer | Optional |
| `charts.us` | — | Optional US chart position data |
| `charts.uk` | — | Optional UK chart position data |
| `featured_artists` | — | Optional featured-artist data |
| `categories` | — | Optional category tags for the song. Previews the [Categories](../roadmap.md) family. |

Enrichment coverage varies by song, and the set of enrichment fields is expanding — see the [roadmap](../roadmap.md). Write your renderer to skip absent fields rather than assuming a fixed shape.

> **Lyrics note:** full lyrics are a separately licensed asset. `lyrics` carries metadata, writer credits and a short preview — not a full lyric sheet. Talk to us if full lyrics matter to your product.

### Pagination and facts

| Field | Notes |
| --- | --- |
| `pagination` | Always present on a successful match — [field reference](../requests-and-responses.md#pagination-in-the-response) |
| `facts.items` | Object keyed by fact ID; values are **HTML**; ordered newest-first (descending fact ID). Omitted when `offset` is out of range. |

## Response: no match

```json
{
  "meta": {
    "status": "ok",
    "matched": false
  },
  "debug": {
    "endpoint": "songfacts",
    "lookup_mode": "name",
    "normalized_input": {
      "artist": "U2",
      "title": "zzzzzzzzzz",
      "limit": 10,
      "offset": 0
    },
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

- `status` is still `"ok"`. No-match is not an error.
- Name-based no-match may include a top-level `best_candidate` when something close existed:

  ```json
  "best_candidate": {
    "id": 933,
    "artist": "U2",
    "title": "One",
    "match_score": 62,
    "match_method": "fuzzy_fulltext_reranked"
  }
  ```

- **ID no-match never includes `best_candidate`**, and may omit `debug.match` entirely when `debug` wasn't requested.

## Response: failure

```json
{ "meta": { "status": "fail", "error": { "type": "auth", "code": "bad_signature" } } }
```

Common cases specific to this endpoint:

| Situation | Response |
| --- | --- |
| Only `title` sent | `invalid_fields` with `artist: "required"` |
| `id` plus `artist` | `invalid_fields` with `id: "not_allowed_with_name_lookup"` |
| `showall` plus `limit` | `invalid_fields` with `showall: "not_allowed_with_pagination"` |
| Client not granted `songfacts` | `access` / `endpoint_not_allowed` |

Full reference: [Error Handling](../errors.md).

## Paging through facts

Popular songs carry dozens of facts. `total` and `not_displayed` tell you what's left.

**Page 1** — `{"artist": "U2", "title": "One"}` → `limit: 10, offset: 0, displayed: 10, total: 23, not_displayed: 13`

**Page 2** — `{"id": 933, "limit": 10, "offset": 10}` → `displayed: 10, not_displayed: 3`

**Page 3** — `{"id": 933, "limit": 10, "offset": 20}` → `displayed: 3, not_displayed: 0`

**Past the end** — `{"id": 933, "offset": 500}` → successful match, `displayed: 0`, no `facts` key.

Note the pattern: resolve once by name, then page by `id`. It's exact, cheaper to match, and immune to matcher variance between pages.

To fetch everything in one call, use `"showall": true` (subject to `showall_not_allowed`), with no `limit` or `offset` alongside it.

## Samples

Signed, runnable versions of both request modes:

- [cURL / bash](../../examples/curl.md)
- [JavaScript (Node.js)](../../examples/javascript.md)
- [Python](../../examples/python.md)
- [PHP](../../examples/php.md)

Product patterns built on this endpoint: [Use Cases](../use-cases.md).
