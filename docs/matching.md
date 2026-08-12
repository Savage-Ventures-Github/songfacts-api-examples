# Matching & Scores

Real catalogs are messy. "U2 – One" arrives as `U2`/`One`, `u2`/`ONE`, `U 2`/`One (Live)` or `U2 feat. Mary J. Blige`. The v5 matcher is built to resolve those to a single correct record — and to tell you how confident it was.

- [How matching works](#how-matching-works)
- [Match methods](#match-methods)
- [Thresholds](#thresholds)
- [Reading the score](#reading-the-score)
- [When nothing matches](#when-nothing-matches)
- [Integration guidance](#integration-guidance)
- [Caching](#caching)

---

## How matching works

Lookups run in two layers:

1. **Normalized matching** — the primary strategy. Input is normalized (case, punctuation, spacing and common noise) and compared against normalized index values. A strong normalized hit scores `100`.
2. **Fuzzy matching** — when normalization doesn't land it exactly, candidates are retrieved with full-text search and re-ranked. Fuzzy matches score **below** `100`.

Every result exposes a match score, and every lookup returns **one best accepted match** — not a ranked list for you to disambiguate. If the best candidate doesn't clear the threshold for the method used, the API reports no match rather than handing you a maybe.

To see what the matcher actually searched on, send `"debug": true` and read `debug.normalized_input`:

```json
"normalized_input": { "artist": "utwo", "title": "on", "limit": 10, "offset": 0 }
```

That's the normalized form — deliberately not your raw input. If a lookup fails unexpectedly, this is the first thing to check.

## Match methods

`debug.match.match_method` uses a small fixed vocabulary.

| Method | Endpoint | When it's used |
| --- | --- | --- |
| `id_exact` | both | Successful ID lookup |
| `normalized_exact` | both | Normalized name match |
| `fuzzy_short_title_artist_pool` | `songfacts` | Title is very short, so candidates are drawn from the normalized artist's pool |
| `fuzzy_fulltext_reranked` | `songfacts` | Standard fuzzy path — artist-and-title full-text retrieval, then re-ranking |
| `fuzzy_title_fulltext_reranked` | `songfacts` | Title-led recovery when artist-and-title retrieval yields no candidates |
| `fuzzy_artist_fulltext_reranked` | `artistfacts` | Artist full-text retrieval, then re-ranking |

## Thresholds

Each method carries its own server-side acceptance threshold. Client-side threshold overrides are **not supported**, and fallback behavior is disabled — if the best match is below its method's threshold, the answer is no match.

| Method | Threshold |
| --- | --- |
| `normalized_exact` | 100 |
| `fuzzy_short_title_artist_pool` | 80 |
| `fuzzy_fulltext_reranked` | 80 |
| `fuzzy_artist_fulltext_reranked` | 80 |
| `fuzzy_title_fulltext_reranked` | 70 |

Responses expose both the score found and the threshold applied, so an accepted match is always auditable:

```json
"match": {
  "match_method": "fuzzy_short_title_artist_pool",
  "match_score": 91,
  "match_threshold_applied": 80
}
```

`match_threshold_applied` is omitted for `id_exact` — an ID lookup either hits or it doesn't.

## Reading the score

Successful `id_exact` matches always report `match_score: 100`.

Name-based lookups can also return a score breakdown, which is useful when you're tuning how much you trust the incoming catalog metadata:

| Field | Meaning |
| --- | --- |
| `match_artist_score` | How well the artist matched |
| `match_title_score` | How well the title matched (`songfacts`) |
| `match_artist_score_contribution` | The artist's weighted contribution to the total |
| `match_title_score_contribution` | The title's weighted contribution (`songfacts`) |
| `match_engine_score` | Search-engine relevance component |
| `match_engine_score_contribution` | Its weighted contribution |
| `match_score_reference_total` | Reference total the contributions sum against |

A worked example — `{"artist": "utwo", "title": "on"}` still resolves to U2's "One":

```json
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
```

These are **diagnostic** fields. The weighting model will keep improving; the score breakdown is there to explain a decision, not to be reimplemented on your side.

## When nothing matches

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

Key points:

- **No-match is `status: "ok"`.** It's a successful API call with an empty result. Never treat it as an error path.
- Name-based no-match keeps `match_method`, `match_score` and `match_threshold_applied` in `debug.match` when `debug` was requested — so you can see whether you missed by 2 points or by 60.
- ID no-match may omit `debug.match` entirely when `debug` wasn't requested.
- Name-based no-match may include a top-level [`best_candidate`](requests-and-responses.md#no-match-and-best_candidate). ID no-match never does.

## Integration guidance

**Do**

- Send the cleanest artist and title you have. The matcher normalizes, but it can't invent information — strip your own player-injected suffixes (`(Remastered 2011)`, `- Radio Edit`, `[Explicit]`) when they're not part of the title.
- Cache the resolved `song.id` / `artist.id` against your own catalog IDs after the first successful lookup, then use ID lookups from then on. It's exact, it's fast, and it removes matcher variance from your product entirely.
- Log `match_method`, `match_score` and `normalized_input` for no-matches during rollout. That log is the fastest way to find systematic catalog mismatches.
- Treat `best_candidate` as a signal for reconciliation or a "did you mean" prompt — not as a silent substitute for a match.

**Don't**

- Don't branch production logic on a specific fuzzy method name. The vocabulary is fixed today but the methods behind it evolve.
- Don't reimplement the scoring weights.
- Don't retry a no-match with mangled variants in a loop — on a metered plan, authenticated no-matches count against your quota.
- Don't expect title-only lookup. `songfacts` by name always needs both `artist` and `title`.

## Caching

The API caches **resolved matches** — the identity a lookup resolved to — shared across all clients, so a popular lookup gets faster for everyone. Facts retrieval stays separate from match caching. The cache key is built from normalized lookup inputs plus the matching strategy, with a 30-day TTL.

Shared match caching is currently suspended while the matcher is under active tuning, which is why `debug.cache_hit` reads `false` in the live runtime today.

Your own caching is still worth it: cache resolved IDs long-term (they're stable), and cache facts payloads for as long as your product's freshness needs allow. New facts are added to existing songs and artists continuously, so don't cache facts forever.

## Next reads

- [Requests & Responses](requests-and-responses.md)
- [`/v5/songfacts`](endpoints/songfacts.md) · [`/v5/artistfacts`](endpoints/artistfacts.md)
- [Use Cases](use-cases.md)
