# `POST /v5/categories`

Browse Songfacts categories, search by text, or return songs for a specific category.

```text
POST https://api.songfacts.com/v5/categories
Content-Type: application/json
```

- [Request model](#request-model)
- [Action: `list`](#action-list)
- [Action: `songs`](#action-songs)
- [Response: successful `list`](#response-successful-list)
- [Response: successful `songs`](#response-successful-songs)
- [Response: no match](#response-no-match)
- [Response: failure](#response-failure)
- [Samples](#samples)

---

## Request model

This endpoint uses an explicit `action` field in the JSON body.

Supported actions:

- `list`
- `songs`

## Action: `list`

Browse the category set, search by text, or narrow by category type.

### Required fields

- `action = "list"`

### Optional fields

- `type`
- `q`
- `sort`
- `limit`
- `offset`
- `debug`

### Example request bodies

Global browse:

```json
{ "action": "list", "sort": "name", "limit": 25, "offset": 0, "debug": true }
```

Search:

```json
{ "action": "list", "q": "ryan tedder", "sort": "relevance", "limit": 10, "offset": 0, "debug": true }
```

List by type:

```json
{ "action": "list", "type": "written-by", "sort": "popularity", "limit": 10, "debug": true }
```

### Sort values

- `type_then_name`
- `name`
- `popularity`
- `relevance` when `q` is present

`popularity` sorts by linked-song count, highest first. It is a volume metric, not editorial ranking or traffic popularity.

`list` is always paginated. `showall` is not allowed for this action.

## Action: `songs`

Return songs linked to a chosen category.

### Required fields

- `action = "songs"`
- one of `category_id` or `category_slug`

### Optional fields

- `artist`
- `sort`
- `limit`
- `offset`
- `showall`
- `debug`

### Example request bodies

By slug:

```json
{ "action": "songs", "category_slug": "songs-written-by-ryan-tedder", "artist": "OneRepublic", "sort": "chart_position", "limit": 10, "debug": true }
```

By ID:

```json
{ "action": "songs", "category_id": 10995, "sort": "year_desc", "limit": 10, "debug": true }
```

### Sort values

- `chart_position` (default)
- `year_desc`
- `year_asc`

When `artist` is provided, the endpoint filters the category-song set to songs by that artist while still returning the normal nested artist metadata for each matching song.

## Response: successful `list`

```json
{
  "meta": { "status": "ok", "matched": true },
  "type": {
    "id": 1,
    "name": "Written By",
    "slug": "written-by"
  },
  "categories": [
    {
      "id": 10995,
      "name": "Songs written by Ryan Tedder",
      "slug": "songs-written-by-ryan-tedder",
      "song_count": 164,
      "type": {
        "id": 1,
        "name": "Written By",
        "slug": "written-by"
      }
    }
  ],
  "pagination": {
    "limit": 10,
    "offset": 0,
    "displayed": 1,
    "total": 75,
    "not_displayed": 74,
    "limit_clamped": true
  }
}
```

When `type` is omitted, the root `type` object is omitted and each category row still carries its own nested `type` object.

## Response: successful `songs`

```json
{
  "meta": { "status": "ok", "matched": true },
  "category": {
    "id": 10995,
    "name": "Songs written by Ryan Tedder",
    "slug": "songs-written-by-ryan-tedder",
    "song_count": 164,
    "type": {
      "id": 1,
      "name": "Written By",
      "slug": "written-by"
    }
  },
  "songs": [
    {
      "id": 9094,
      "title": "Bleeding Love",
      "slug": "bleeding-love",
      "url": "https://www.songfacts.com/facts/leona-lewis/bleeding-love",
      "release_date": "2007",
      "chart_position": 1,
      "charts": {
        "us": 1,
        "uk": 1
      },
      "artist": {
        "id": 43613,
        "name": "Leona Lewis",
        "slug": "leona-lewis",
        "url": "https://www.songfacts.com/songs/leona-lewis"
      }
    }
  ],
  "pagination": {
    "limit": 10,
    "offset": 0,
    "displayed": 1,
    "total": 164,
    "not_displayed": 163,
    "limit_clamped": true
  }
}
```

## Response: no match

- `meta.status` stays `"ok"`
- `meta.matched = false`
- `list` no-match means a provided `type` did not resolve to a known category type
- `songs` no-match means the requested category did not resolve

## Response: failure

```json
{ "meta": { "status": "fail", "error": { "type": "request", "code": "invalid_fields" } } }
```

Common validation rules:

- `category_slug` and `category_id` must not be sent together
- `artist` is allowed only on `action = "songs"`
- `showall` is not allowed on `action = "list"`
- `relevance` is allowed only when `q` is present
- `showall` cannot be combined with `limit` or `offset`

Category display names are normalized before they are served by the API and do not include the legacy `More ` prefix.

## Samples

- [cURL / bash](../../examples/curl.md)
- [JavaScript (Node.js)](../../examples/javascript.md)
- [Python](../../examples/python.md)
- [PHP](../../examples/php.md)
