# Use Cases

Worked patterns for the product surfaces the Songfacts API is built for. Each one shows the calls involved, what you get back, and the practical notes that only show up once you've built it.

**Available today** with `/v5/songfacts` and `/v5/artistfacts`:

- [1. Now Playing enrichment](#1-now-playing-enrichment)
- [2. Song detail pages](#2-song-detail-pages)
- [3. Artist pages and bio modules](#3-artist-pages-and-bio-modules)
- [4. Editorial and CMS enrichment](#4-editorial-and-cms-enrichment)
- [5. Radio, podcast and voice segments](#5-radio-podcast-and-voice-segments)
- [6. Newsletters and social content](#6-newsletters-and-social-content)

**Coming with the next endpoint families:**

- [7. Rabbit-hole discovery (Categories)](#7-rabbit-hole-discovery-categories)
- [8. This day in music (Calendar)](#8-this-day-in-music-calendar)
- [9. Quotes modules](#9-quotes-modules)
- [10. Compact surfaces (Blurbs)](#10-compact-surfaces-blurbs)
- [11. Trivia and games](#11-trivia-and-games)

Also: [Implementation patterns that apply everywhere](#implementation-patterns-that-apply-everywhere)

---

## 1. Now Playing enrichment

**The surface:** a player, in-car display, smart speaker screen or TV app shows what's playing. You want a line of genuine story under the track, not credits.

**The call:** one `songfacts` lookup keyed on the metadata you already have.

```json
{ "artist": "Electric Light Orchestra", "title": "Don't Bring Me Down", "limit": 1 }
```

**What you render:** the top item from `song.facts.items` — the newest fact, which is generally the most current and most interesting.

> Wondering why Jeff Lynne repeatedly sings the word "groose" after the song's title line? Apparently it was a made-up place-keeper word to fill a gap in the vocals when he was improvising the lyrics. When the German engineer Reinhold Mack heard the ELO frontman's demo, he asked Lynne how he knew "gruss" means "greetings" in his country's language. Upon learning the German meaning, Lynne decided to leave it in.

**Notes from the field:**

- **Clean your title first.** Player metadata often carries `(Remastered 2011)`, `- Radio Edit` or `[Explicit]`. Strip suffixes that aren't part of the title; the matcher normalizes, but it can't know your conventions.
- **Cache the resolved `song.id`** against your catalog ID on first lookup, then use ID lookups forever after. Exact, fast, no matcher variance.
- **Prefetch on the queue, not on track change.** Resolve the next few tracks while the current one plays so the panel is populated the instant the track flips.
- **Design the empty state.** Long-tail catalog tracks won't have a record. `matched: false` should collapse the module, never show an error.
- Link the fact to `song.url` for attribution and a click-through.

## 2. Song detail pages

**The surface:** a full page or expanded sheet for one track.

**The calls:**

```json
{ "artist": "Taylor Swift", "title": "Opalite", "debug": true }
```

Then page through the rest as the user scrolls:

```json
{ "id": 12345, "limit": 10, "offset": 10 }
```

**What you get in one response:** the facts, plus everything needed to build the page frame — `song.title`, `song.url`, nested `song.artist` (name and link), `song.youtube.url` for an embed, `song.lyrics.writers` for a credits line, and optional `album`, `release_year` and `charts`.

**Notes:**

- `pagination.total` gives you the fact count up front — useful for "12 facts about this song" headers and for deciding between a full list and a "show more" control.
- Facts are HTML with inline `<i>` and `<b>`. Sanitize against your own allowlist if you're strict about markup, but render it as HTML — there's no plain-text alternative field.
- `song.artist.id` links straight into an [Artistfacts](#3-artist-pages-and-bio-modules) call with no second name match.

## 3. Artist pages and bio modules

**The surface:** an artist page, or an "about the artist" panel.

**The call:**

```json
{ "artist": "Gorillaz" }
```

Or, arriving from a song: `{ "id": <song.artist.id> }`.

**What you render:** `artist.facts.items` as a scrollable feed of curated background, plus optional `dates_of_existence` and `members` for a fact strip.

> Gorillaz are a virtual band created by Damon Albarn and Jamie Hewlett, with Albarn in charge of the music and Hewlett the visuals. Albarn is the frontman for the British rock group Blur; Hewlett draws comic books and is most famous for Tank Girl, which in 1995 was made into a movie starring rapper Ice-T and Stooges frontman Iggy Pop. Hewlett says it's best to watch the film with the volume muted.

**Notes:**

- `artistfacts` returns **artist-only** data — no related-song list. Combine with your own catalog for the discography.
- Artist facts change less often than song facts, so they cache well. Long TTLs are safe here.
- Established artists have deep fact sets: `limit: 3` for a teaser module, paging for the full page.

## 4. Editorial and CMS enrichment

**The surface:** an article, review or roundup where an editor wants supporting context on the songs mentioned.

**The pattern:** in your CMS, a lookup widget calls `songfacts` with `debug: true` and shows the editor the matched title, the score, and the fact list. The editor picks facts by ID; you store the fact IDs alongside the article and re-fetch at render time so the content stays current.

**Notes:**

- **Store fact IDs, not fact text.** Facts get updated and expanded; storing IDs means your article gets the corrected copy for free.
- `debug.match.match_score` and `debug.normalized_input` let an editor confirm the match is the right record before they publish — worth surfacing in the widget.
- If a lookup returns `best_candidate`, show it as a "did you mean" suggestion with its score. Editors are good at this call; automated systems aren't.
- Always render the `song.url` / `artist.url` attribution link.

## 5. Radio, podcast and voice segments

**The surface:** DJ patter, an automated between-tracks voice segment, a podcast prep sheet, or a smart-speaker "tell me about this song" response.

**The pattern:** resolve the playlist ahead of air with ID lookups, then pull one fact per track — rotating by fact ID so the same track doesn't produce the same line twice in a week.

```json
{ "id": 933, "limit": 5 }
```

**Notes:**

- **Fact IDs make rotation trivial.** Keep a per-track set of IDs you've already used and pick an unused one.
- For text-to-speech, strip the HTML tags — the markup is presentational.
- Prep the whole hour in one batch rather than calling live. It's more predictable on air and easier on your quota.

## 6. Newsletters and social content

**The surface:** a weekly "story behind the song" email, or a social post series.

**The pattern:** pull facts for a curated set of tracks on a schedule, dedupe against what you've already sent using fact IDs, and template the copy.

**Notes:**

- The newest fact for a song is often tied to something recent — a re-release, a sync placement, a tour announcement — which makes newest-first ordering naturally topical.
- Fact HTML drops cleanly into email templates; check that your email builder doesn't strip `<i>` / `<b>`.
- Attribution links back to `song.url` drive traffic and satisfy your license terms.

---

The following four families exist in the Songfacts archive and are in progress as API endpoints. Content samples are live now; request and response contracts are not final. See the [roadmap](roadmap.md).

## 7. Rabbit-hole discovery (Categories)

**The surface:** "if you liked this, here's the thread it belongs to." The strongest retention feature in the set.

Songfacts has **over 800 curated categories** that connect songs by theme — the kind of connection a recommendation engine can't infer from audio features:

| A song | leads to a category | which leads to |
| --- | --- | --- |
| "Dreams" — Fleetwood Mac | More songs about unhealthy relationships | "Happier Than Ever" (Billie Eilish), "Love on the Brain" (Rihanna), "Misery" (Maroon 5) |
| "Whose Bed Have Your Boots Been Under?" — Shania Twain | More songs written or produced by Mutt Lange | "Highway to Hell" (AC/DC), "Pour Some Sugar On Me" (Def Leppard) |
| "Chop Suey" — System Of A Down | More songs with food in the title | "Ice Cream" (BlackPink), "On The Good Ship Lollipop" (Shirley Temple) |
| "I Wanna Be Down" — Brandy | More songs recorded by an artist who was very young | "It's My Party" (Lesley Gore), "Fingertips (Part 2)" (Stevie Wonder) |

Others in the set: songs written by Bruno Mars · songs produced by T Bone Burnett · songs covered by Bruce Springsteen · songs featuring a cowbell · examples of glam rock · songs inspired by Emily Brontë's *Wuthering Heights* · songs about a fresh start · songs that won Oscars · songs that became hits when re-released · songs used in Spider-Man · songs with city names in the title · songs with videos directed by Hannah Lux Davis · songs popular at Halloween · songs that are spoofs, satires or parodies.

**Planned capability:** list categories, and list songs by category ID or category name. The endpoint family will use an explicit `action` field in the JSON body for multi-operation families.

**Preview it today:** successful `songfacts` matches may already include an optional `categories` field on the song object. [Live samples](https://api.songfacts.com/categories-examples)

## 8. This day in music (Calendar)

**The surface:** a daily-return feature — "on this day in music" — or an artist career timeline.

Songfacts has been building its music history calendar since 2007. The value proposition is editorial judgment: the other lists are incomplete, arcane, or rock-only.

**By date** — April 25:

> **2006** — Bruce Springsteen releases *We Shall Overcome: The Seeger Sessions*, a collection of songs popularized by Pete Seeger. The album brings Seeger into the spotlight. "I've managed to survive all these years by keeping a low profile," Seeger says. "Now my cover's blown."
>
> **1994** — A jury rules that Michael Bolton's 1991 hit "Love Is a Wonderful Thing" plagiarizes The Isley Brothers' 1966 song of the same name and awards $5.4 million in damages, the largest ever in a music plagiarism case.
>
> **1970** — The Jackson 5 bump The Beatles ("Let It Be") off the top spot in America with "ABC."

**By artist** — Miley Cyrus:

> **November 23, 1992** — Miley Cyrus is born Destiny Hope Cyrus in Franklin, Tennessee. Nicknamed "Smiley," later shortened to "Miley," she is the first child of country star Billy Ray Cyrus. She also has a famous godmother: Dolly Parton.
>
> **February 4, 2024** — Miley Cyrus wins her first pair of Grammys when "Flowers" takes Record Of The Year and Best Pop Solo Performance.

**Planned capability:** events by date and by artist. Ideal for a widget that gives users a reason to open the app daily. [Live samples](https://api.songfacts.com/music-history-calendar-examples)

## 9. Quotes modules

**The surface:** a pull-quote in an article, a card in a player, or a "in their own words" panel on an artist page.

Direct quotes from artists, songwriters and producers, pulled from the Songfacts and American Songwriter interview archives:

> "The song is really fun, but I can remember those feelings of betrayal."
> — **Melissa Etheridge** on "Bring Me Some Water"

> "Instinctively, musicians are lazy. They are some of the laziest people in the world."
> — **Buzz Osborne** of the Melvins, on musicians

> "The record label wasn't around, and in those days we were so big, we didn't really listen to anybody. We could do 10-minute long songs."
> — **Matt Sorum** on "November Rain" by Guns N' Roses

Quotes carry an attributed speaker and a subject — either a specific song or a topic — which makes them easy to place against a track or an artist you're already showing. [Live samples](https://api.songfacts.com/quotes-examples)

## 10. Compact surfaces (Blurbs)

**The surface:** anywhere a full fact won't fit — a now-playing card, a tooltip, a lock screen, a watch face, a carousel tile, a push notification.

Blurbs are compressed versions of select Songfacts and Artistfacts, **under 280 characters**:

> Taylor Swift used a line from "Blank Space" on party favors at her wedding to Travis Kelce: "So it's gonna be forever..." It's Kelce's favorite Taylor Swift song.

> The Lumineers crashed the nu-folk revival of the 2010s with their stomp-and-clap anthem "Ho Hey." The deceptively upbeat song about unrequited love introduced a winning formula of pairing melancholy lyrics with feel-good melodies.

Available for both songs and artists. Until the endpoint ships, `limit: 1` on a full facts lookup plus your own truncation is the closest approximation — but blurbs are editorially compressed, not cut off mid-sentence. [Live samples](https://api.songfacts.com/blurbs-examples)

## 11. Trivia and games

**The surface:** an in-app quiz, a game show segment, a listener contest, an engagement loop between tracks.

Songfacts has been writing music trivia for decades. Questions come with an answer set, an explanation, and often an audio clue:

> **What male solo artist had #1 Hot 100 hits in the 1990s, 2000s, and 2010s?**
> Elton John · **Usher** · Justin Timberlake · Jay-Z
>
> *Usher had nine #1 hits, starting with "Nice & Slow" in 1998 and ending with "OMG" in 2010.*

> **What B-52's song inspired the title of a '90s movie?**
> "Love Shack" · "Rock Lobster" · "Deadbeat Club" · **"Private Idaho"**
>
> *"Private Idaho" provided the title to the 1991 movie My Own Private Idaho, starring River Phoenix and Keanu Reeves.*

Trivia is **customized for each client** — difficulty, era, genre focus and format are set per integration, so start with a conversation rather than a schema. [Live samples](https://api.songfacts.com/trivia-examples)

---

## Implementation patterns that apply everywhere

**Resolve once, then use IDs.** The single most valuable habit. Match by name on first encounter, store `song.id` / `artist.id` against your own catalog ID, and use ID lookups from then on. Exact matching, no matcher variance, no wasted no-match calls.

**Batch and prefetch, don't call inline.** Resolve the queue, the playlist, the article's tracklist or the hour's rotation ahead of time. Never block a render on a network call you could have made a minute earlier.

**Treat no-match as a design state.** Every real catalog has tracks with no Songfacts record. The module should collapse cleanly. If your UI needs a fallback, an Artistfacts lookup on the same track's artist is a good one — artist coverage is broad even where a specific B-side isn't covered.

**Cache with intent.** Resolved IDs are stable and can be cached indefinitely. Facts are actively expanded and edited — cache them for hours or days, not forever.

**Store fact IDs for anything editorially curated.** Fact IDs are stable identifiers. Use them for dedupe, rotation, "already shown to this user," and re-fetching at render time so corrections propagate.

**Render the attribution link.** `song.url` and `artist.url` are the canonical Songfacts pages. Linking them is good practice, drives traffic both ways, and is typically a license requirement.

**Log diagnostics during rollout.** Send `debug: true` in staging and log `normalized_input`, `match_method` and `match_score` for every no-match. A week of that log tells you exactly where your catalog metadata diverges from the Songfacts index — usually in a small number of fixable patterns.

**Mind what counts against quota.** Successful matches and authenticated no-matches both count. Auth failures and malformed requests don't. So retry-with-variants loops on a no-match are the expensive mistake to avoid.

## Next reads

- [`/v5/songfacts`](endpoints/songfacts.md) · [`/v5/artistfacts`](endpoints/artistfacts.md)
- [Matching & Scores](matching.md) — get name lookups landing reliably
- [Roadmap](roadmap.md) — what's coming and when to plan for it
- [Code samples](../examples/README.md)
