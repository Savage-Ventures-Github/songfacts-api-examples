# Roadmap

What's live, what's next, and what stays stable while the API grows.

- [Available now](#available-now)
- [Planned endpoint families](#planned-endpoint-families)
- [Platform enhancements](#platform-enhancements)
- [What won't change](#what-wont-change)
- [Influencing the roadmap](#influencing-the-roadmap)

This page describes direction, not commitments. Timelines and contracts for unreleased features are subject to change; nothing here should be treated as a delivery date.

---

## Available now

| Endpoint | Status |
| --- | --- |
| `POST /v5/songfacts` | Live — lookup by ID or artist + title, paginated facts, matcher diagnostics |
| `POST /v5/artistfacts` | Live — lookup by ID or artist, paginated facts, matcher diagnostics |

The platform underneath: HMAC-SHA256 request signing with replay protection, per-client endpoint grants, normalized plus fuzzy matching with exposed confidence scores, and shared match caching.

## Planned endpoint families

The v5 contract was designed so new families slot in alongside the existing ones. Nothing below requires changes to a `songfacts` or `artistfacts` integration.

### Categories

Over 800 curated tags that connect songs by theme — the retention feature of the set. Planned capabilities:

- **List categories** — browse or search the full category set
- **List songs by category** — by category ID or category name

Multi-operation families like this one will use a single endpoint family plus an explicit `action` field in the JSON body, rather than a proliferation of paths.

**Already visible:** successful `songfacts` matches may include an optional `categories` field on the song object today. The request/response contract for the standalone family is still being finalized.

[Content samples](https://api.songfacts.com/categories-examples) · [Use case](use-cases.md#7-rabbit-hole-discovery-categories)

### Music History Calendar

Significant events in music history, built continuously since 2007. Planned capabilities:

- **Events by date** — for this-day-in-music features
- **Events by artist** — for career timelines

[Content samples](https://api.songfacts.com/music-history-calendar-examples) · [Use case](use-cases.md#8-this-day-in-music-calendar)

### Quotes

Direct quotes from artists, songwriters and producers, drawn from the Songfacts and American Songwriter interview archives, with attributed speaker and subject.

[Content samples](https://api.songfacts.com/quotes-examples) · [Use case](use-cases.md#9-quotes-modules)

### Blurbs

Editorially compressed versions of select Songfacts and Artistfacts, under 280 characters, for cards, tooltips, notifications and other constrained surfaces. Available for both songs and artists.

[Content samples](https://api.songfacts.com/blurbs-examples) · [Use case](use-cases.md#10-compact-surfaces-blurbs)

### Trivia

Question, answer-set, explanation and audio-clue payloads. Trivia is customized per client — difficulty, era, genre focus and format are set per integration — so this family starts with a conversation about your format.

[Content samples](https://api.songfacts.com/trivia-examples) · [Use case](use-cases.md#11-trivia-and-games)

## Platform enhancements

### Usage reporting and analytics

Detailed per-client analytics with six-month retention, covering request volume, match outcomes, client behavior and quota consumption — surfaced back to clients as usage reporting, not just kept internally. Currently the runtime implements metered quota counting for trial and limited keys; the full analytics layer is in progress.

### Media enrichment

Thumbnail and presentation-oriented image assets delivered inside the matched object, served from `img.songfacts.com`. This unlocks visual now-playing cards and artist tiles without you sourcing imagery separately.

### Richer song and artist payloads

Continued expansion of denormalized enrichment fields. Already appearing on `songfacts` matches where available: nested artist, YouTube, lyrics metadata, album, release year, US and UK chart positions, featured artists and categories. On `artistfacts`: dates of existence and members.

Because optional fields are **omitted when unavailable** rather than returned as `null`, new enrichment fields appear additively — write your renderer to check for key presence and it keeps working as coverage grows.

### Shared match caching

A cross-client cache of resolved matches (30-day TTL), so popular lookups resolve faster for everyone. Facts retrieval stays separate from match caching. Currently suspended while the matcher is under active tuning, which is why `debug.cache_hit` reads `false` in the live runtime.

### Matcher improvements

Ongoing tuning of normalization, candidate retrieval and re-ranking. Match methods and score weighting are exposed as **diagnostics, not contract** precisely so this can improve without breaking integrations — which is why you shouldn't branch production logic on a specific fuzzy method name.

### Per-client controls

The client model is built to carry configurable quota and rate policy per client without a redesign. Today, trial and limited keys are metered and full clients are unmetered.

## What won't change

The parts of the contract you can safely build on:

| | |
| --- | --- |
| **URL structure** | `api.songfacts.com/{version}/{endpoint}`. Versioning lives in the path, so a future contract change arrives as a new version — not as a change under your feet. |
| **Transport** | `POST` + JSON, `snake_case` fields. |
| **Signing** | HMAC-SHA256 over the five-line canonical string. New endpoint families use the identical signing contract, so onboarding to a new family costs you no auth work. |
| **Response envelope** | `meta.status` / `meta.matched`, matched data under an endpoint-specific top-level key, facts nested inside it, always HTTP `200`. |
| **The failure model** | Fixed `type` / `code` vocabulary. New codes may be added; existing ones keep their meaning. |
| **IDs** | Song, artist and fact IDs are stable. Cache them. |

Practical guidance for staying forward-compatible:

1. **Ignore unknown response fields** rather than failing validation on them — enrichment is additive.
2. **Check for key presence** on optional fields instead of assuming a fixed shape.
3. **Handle unrecognized error codes** by falling back on `error.type`, which is a small, stable set.
4. **Don't depend on `debug`** in production logic. Log it, alert on it, don't branch on it.

## Influencing the roadmap

Priority follows real integrations. If a family on this page is the reason you'd adopt the API — or if there's something in the Songfacts archive you need that isn't listed — say so in the [access request form](https://api.songfacts.com/#get-started) or reply to our follow-up. Client demand is how these get sequenced, and several of the families above are already client-driven.

## Next reads

- [Use Cases](use-cases.md) — what these families are for
- [Requests & Responses](requests-and-responses.md) — the conventions new families will follow
- [Get Access](../README.md#get-access-to-songfacts-api)
