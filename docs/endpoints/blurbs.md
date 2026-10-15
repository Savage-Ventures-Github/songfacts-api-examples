# `POST /v5/blurbs`

Resolve a song blurb or an artist blurb.

This endpoint returns matched song or artist metadata in the same general shape as `songfacts` and `artistfacts`, but instead of returning the full `facts.items` payload it returns a single `blurb` string.

```text
POST https://api.songfacts.com/v5/blurbs
Content-Type: application/json
```

- [Request modes](#request-modes)
- [Optional fields](#optional-fields)
- [Response: successful match](#response-successful-match)
- [Response: no match](#response-no-match)
- [Response: failure](#response-failure)
- [Samples](#samples)

---

## Request modes

### By artist

Required field:

- `artist`

```json
{ "artist": "Taylor Swift", "debug": true }
```

### By artist and title

Required fields:

- `artist`
- `title`

```json
{ "artist": "U2", "title": "One", "debug": true }
```

When `title` is present, the API first looks for a song blurb. If no song blurb exists for the matched song, it falls back to the matched artist blurb for that artist when one exists.

ID lookup is not supported in v1.1.

## Optional fields

- `debug`

This endpoint does not accept pagination fields. Each request returns at most one blurb payload.

## Response: successful match

Artist-mode example:

```json
{
  "meta": { "status": "ok", "matched": true },
  "artist": {
    "id": 42456,
    "name": "Taylor Swift",
    "slug": "taylor-swift",
    "url": "https://www.songfacts.com/facts/taylor-swift",
    "blurb": "Example artist blurb text"
  }
}
```

Song-mode matches return the matched `song` object with a single `blurb` field instead of a `facts.items` payload.

## Response: no match

Name-based no-match responses return `meta.status = "ok"` and `meta.matched = false`.

In song mode, `matched = false` can mean either no song match was found, or a song matched but neither a song blurb nor a fallback artist blurb was available for that artist.

When a strong candidate exists, the response may also include `best_candidate` with the candidate `id`, `artist`, and current diagnostic match metadata.

## Response: failure

```json
{ "meta": { "status": "fail", "error": { "type": "request", "code": "invalid_fields" } } }
```

Common validation notes:

- `artist` is always required
- `limit`, `offset`, and `showall` are rejected
- unknown fields are rejected

Song-mode requests use the same matcher family as `songfacts` name lookups. Artist-mode requests use the same matcher family as `artistfacts` name lookups.

## Samples

- [cURL / bash](../../examples/curl.md)
- [JavaScript (Node.js)](../../examples/javascript.md)
- [Python](../../examples/python.md)
- [PHP](../../examples/php.md)
