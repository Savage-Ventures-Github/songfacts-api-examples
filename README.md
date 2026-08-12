# Songfacts API v5 — Examples & Developer Guide

**The stories behind the songs and artists, delivered as ready-to-read Songfacts® and Artistfacts your app can drop straight into any music experience.**

This repository is the public companion to [api.songfacts.com](https://api.songfacts.com/). It explains what the Songfacts API v5 offers, and gives developers copy-pasteable request signing code, real request/response samples, and worked use-cases.

- **Base URL:** `https://api.songfacts.com`
- **Path shape:** `https://api.songfacts.com/{version}/{endpoint}` — e.g. `https://api.songfacts.com/v5/songfacts`
- **Transport:** `POST` + JSON only, authenticated with HMAC-SHA256 signed headers
- **Status:** v5 is live for `songfacts` and `artistfacts`; more endpoint families are in progress

---

## Table of Contents

- [What Is the Songfacts API?](#what-is-the-songfacts-api)
- [Why the Data Is Different](#why-the-data-is-different)
- [Available Now](#available-now)
- [Coming Soon](#coming-soon)
- [A Call in 60 Seconds](#a-call-in-60-seconds)
- [Documentation](#documentation)
- [Use Cases](#use-cases)
- [Code Samples](#code-samples)
- [Get Access to Songfacts API](#get-access-to-songfacts-api)
- [Support](#support)

---

## What Is the Songfacts API?

Songfacts has been building its database of song and artist stories since 1999, and is now owned by [American Songwriter](https://americansongwriter.com/). The API exposes that editorial archive as clean, structured JSON.

You send an artist and title (or a Songfacts ID). The API resolves it to the right record — exact match or fuzzy match, with a confidence score you can see — and returns the full set of facts for it, newest first, as display-ready HTML.

The v5 API is designed for server-to-server integration:

| | |
| --- | --- |
| **Method** | `POST` only. No `GET` for identified operations. |
| **Body** | JSON only, `Content-Type: application/json`. Fields use `snake_case`. |
| **Auth** | `client_id` + shared secret, HMAC-SHA256 request signing, replay-protected with a timestamp and nonce. |
| **HTTP status** | Always `200`. Success and failure are expressed in the JSON body under `meta`. |
| **Cardinality** | One best accepted match per lookup — not a list of guesses to disambiguate on your side. |
| **Facts format** | HTML strings keyed by fact ID, ordered newest to oldest, paginated. |

## Why the Data Is Different

Far beyond standard metadata, these facts are the kind of talking points your music-obsessed friend is always telling you about — written by real human music experts, not generated summaries. They've been used for years by streaming services and broadcasters to engage audiences and stand out from services that supply only credits.

A Songfact:

> **"Don't Bring Me Down" — Electric Light Orchestra**
> Wondering why Jeff Lynne repeatedly sings the word "groose" after the song's title line? Apparently it was a made-up place-keeper word to fill a gap in the vocals when he was improvising the lyrics. When the German engineer Reinhold Mack heard the ELO frontman's demo, he asked Lynne how he knew "gruss" means "greetings" in his country's language. Upon learning the German meaning, Lynne decided to leave it in.

An Artistfact:

> **Gorillaz**
> Gorillaz are a virtual band created by Damon Albarn and Jamie Hewlett, with Albarn in charge of the music and Hewlett the visuals. Albarn is the frontman for the British rock group Blur; Hewlett draws comic books and is most famous for Tank Girl, which in 1995 was made into a movie starring rapper Ice-T and Stooges frontman Iggy Pop. Hewlett says it's best to watch the film with the volume muted.

More samples: [Songfacts examples](https://api.songfacts.com/songfacts-examples) · [Artistfacts examples](https://api.songfacts.com/artistfacts-examples)

## Available Now

| Endpoint | What it returns | Docs |
| --- | --- | --- |
| `POST /v5/songfacts` | Song identity (title, slug, canonical URL, nested artist, YouTube, lyrics metadata) plus that song's full facts | [docs/endpoints/songfacts.md](docs/endpoints/songfacts.md) |
| `POST /v5/artistfacts` | Artist identity (name, slug, canonical URL) plus that artist's full facts | [docs/endpoints/artistfacts.md](docs/endpoints/artistfacts.md) |

Both accept lookup by `id` or by name, support `limit` / `offset` pagination, and can return matcher diagnostics with `debug: true`.

## Coming Soon

These content families exist in the Songfacts archive and are being prepared as API endpoints. Content samples are live on the landing pages today; request and response contracts are not final.

| Family | What it is | Samples |
| --- | --- | --- |
| **Categories** | 800+ curated tags that connect songs by theme — "songs about unhealthy relationships," "songs featuring a cowbell," "songs written or produced by Mutt Lange." Built for rabbit-hole browsing. | [Examples](https://api.songfacts.com/categories-examples) |
| **Music History Calendar** | The biggest moments in music history for any given date, or across an artist's career. Built since 2007. | [Examples](https://api.songfacts.com/music-history-calendar-examples) |
| **Quotes** | Direct quotes from artists, songwriters and producers, pulled from the Songfacts and American Songwriter interview archives. | [Examples](https://api.songfacts.com/quotes-examples) |
| **Blurbs** | Compressed versions of select Songfacts and Artistfacts, under 280 characters, sized for cards, tooltips and now-playing screens. | [Examples](https://api.songfacts.com/blurbs-examples) |
| **Trivia** | Multiple-choice music trivia with answers and audio clues, customized per client. | [Examples](https://api.songfacts.com/trivia-examples) |

See [docs/roadmap.md](docs/roadmap.md) for the full planned expansion, including platform-level enhancements.

## A Call in 60 Seconds

**1. Build the JSON body.**

```json
{ "artist": "U2", "title": "One" }
```

**2. Build the canonical string** — five lines, joined with `\n`: method, path, Unix timestamp, nonce, and the lowercase hex SHA-256 of the exact raw body bytes.

```text
POST
/v5/songfacts
1718064000
abc123_nonce_token
0b3e4e625d89234675099ff36b5b5b8f3d7ecb270504ac0c31fbe912ca9fbd64
```

**3. Sign it** with HMAC-SHA256 using your shared secret, lowercase hex output, and send it in the headers.

```bash
curl -sS -X POST https://api.songfacts.com/v5/songfacts \
  -H 'Content-Type: application/json' \
  -H "X-Client-Id: your-client-id" \
  -H "X-Timestamp: 1718064000" \
  -H "X-Nonce: abc123_nonce_token" \
  -H "X-Signature: <64-char lowercase hex hmac>" \
  --data-binary '{"artist":"U2","title":"One"}'
```

**4. Read the response.** `meta.status` tells you the request was processed; `meta.matched` tells you whether a record was found.

```json
{
  "meta": { "status": "ok", "matched": true },
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
        "190815": "U2 recorded a new version of \"One\" for their 2023 album <b>Songs Of Surrender</b>. It debuted at the Super Bowl that year as part of the Walter Payton Man Of The Year presentation."
      }
    }
  }
}
```

Full walkthrough: [docs/quick-start.md](docs/quick-start.md).

## Documentation

| Guide | Covers |
| --- | --- |
| [Quick Start](docs/quick-start.md) | Credentials, first signed request, reading the response |
| [Authentication & Signing](docs/authentication.md) | Header contract, canonical string, body hash, replay protection, `bad_signature` troubleshooting |
| [Requests & Responses](docs/requests-and-responses.md) | Field rules, validation, pagination, response envelope |
| [Matching & Scores](docs/matching.md) | Exact vs. fuzzy matching, thresholds, match methods, `best_candidate` |
| [Error Handling](docs/errors.md) | Failure model, every error type and code, no-match semantics |
| [Endpoint: `/v5/songfacts`](docs/endpoints/songfacts.md) | Request modes, full response reference, samples |
| [Endpoint: `/v5/artistfacts`](docs/endpoints/artistfacts.md) | Request modes, full response reference, samples |
| [Use Cases](docs/use-cases.md) | Worked integrations for real product surfaces |
| [Roadmap](docs/roadmap.md) | Planned endpoints and platform enhancements |

## Use Cases

[docs/use-cases.md](docs/use-cases.md) walks through the product surfaces the API is built for, each with the calls that power it:

- **Now Playing / song detail enrichment** — one lookup per track, keyed on the artist and title you already have
- **Artist pages and bio modules** — Artistfacts as a scrollable "about" feed
- **Editorial and CMS enrichment** — attach facts to articles, reviews and newsletters
- **Playlist and radio segments** — pull a fact per track for DJ patter, voice assistants or on-screen cards
- **Engagement features** — rabbit-hole browsing, this-day-in-music, quotes and trivia (using the families in progress)

## Code Samples

Signed, runnable examples for the four core flows — songfacts by ID, songfacts by name, artistfacts by ID, artistfacts by name:

- [cURL / bash](examples/curl.md)
- [JavaScript (Node.js)](examples/javascript.md)
- [Python](examples/python.md)
- [PHP](examples/php.md)

All samples read `BASE_URL`, `CLIENT_ID` and `CLIENT_SECRET` from the environment. See [examples/README.md](examples/README.md).

---

## Get Access to Songfacts API

API access is issued per client. Credentials are provisioned by Songfacts — there is no self-serve signup — so the first step is to tell us about your app.

### 1. Request access

**→ [Fill out the access request form at api.songfacts.com](https://api.songfacts.com/#get-started)**

Tell us who you are and describe your application and how you'd like to use the data. We'll follow up to talk through fit, volume and licensing.

### 2. Ask about a free trial account

**Trial accounts are available on request.** A trial client is provisioned with a real `client_id` and shared secret and an allowance of **500 free API calls** — enough to build a working integration against live data, evaluate match quality on your own catalog, and show your team something real before any commercial conversation.

Trial notes:

- Trial credentials use the same signing contract and the same endpoints as production. Nothing needs to be rewritten when you graduate.
- Successful matches **and** authenticated no-match results count against your allowance.
- Auth failures and malformed requests do **not** count against it, so getting your signing code working costs you nothing.
- Once the allowance is consumed, calls return `meta.error.code = "quota_exceeded"`. Just get in touch and we'll take it from there.

Mention "trial account" in the form's description field, or reply to our follow-up email, and we'll set one up.

### 3. What's planned next

The v5 contract was designed so new content families slot in without breaking existing integrations. What's on the roadmap:

**New endpoint families**

- **Categories** — list the 800+ Songfacts categories, and list songs within a category by ID or name. Turns a single song lookup into a browsable graph of related songs.
- **Music History Calendar** — significant music events by date or by artist, for this-day-in-music features.
- **Quotes** — artist, songwriter and producer quotes from the Songfacts and American Songwriter interview archives.
- **Blurbs** — sub-280-character versions of select facts for constrained UI.
- **Trivia** — question, answer-set and clue payloads, customized per client.

**Platform enhancements**

- **Usage reporting** — detailed per-client analytics on request volume, match outcomes and quota consumption, retained for six months and surfaced back to clients.
- **Media enrichment** — thumbnail and image assets served from `img.songfacts.com`, delivered inside the matched object.
- **Richer song payloads** — continued expansion of denormalized fields such as album, release year, chart positions, featured artists and categories.
- **Shared match caching** — a cross-client resolved-match cache for faster repeat lookups.
- **Per-client controls** — configurable quotas and rate policy on the client model.

Details and current status: [docs/roadmap.md](docs/roadmap.md).

---

## Support

- **Access requests and trials:** [api.songfacts.com](https://api.songfacts.com/#get-started)
- **General contact:** [feedback@songfacts.com](mailto:feedback@songfacts.com)
- **Docs corrections and sample fixes:** open an issue or a pull request on this repository

### Legal

Songfacts® is a registered trademark of Songfacts, LLC. Facts returned by the API are licensed editorial content — use is governed by your API agreement and the [Songfacts Terms of Service](https://www.songfacts.com/blog/pages/terms-of-service). The code samples in this repository are provided as integration references.

© Songfacts, LLC
