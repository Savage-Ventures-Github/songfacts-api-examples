# `POST /v5/calendar`

Browse music history calendar categories, browse timelines, or return entries for a requested date.

```text
POST https://api.songfacts.com/v5/calendar
Content-Type: application/json
```

- [Request model](#request-model)
- [Action: `categories`](#action-categories)
- [Action: `entries`](#action-entries)
- [Action: `timeline`](#action-timeline)
- [Response excerpts](#response-excerpts)
- [Response: failure](#response-failure)
- [Samples](#samples)

---

## Request model

This endpoint uses an explicit `action` field in the JSON body.

Supported actions:

- `categories`
- `entries`
- `timeline`

Shared rules:

- requests are signed `POST` JSON requests
- `debug` is optional on all calendar actions
- `limit`, `offset`, and `showall` are supported on all calendar actions
- default pagination is `limit = 20`, `offset = 0`, `showall = false`
- if `showall = true`, `limit` and `offset` must not be sent
- calendar entry responses do not include YouTube or other video payloads

## Action: `categories`

Browse or search calendar categories, or fetch entries for a specific category.

Browse/search example:

```json
{ "action": "categories", "q": "album", "limit": 20, "offset": 0, "debug": true }
```

Category entries by slug:

```json
{ "action": "categories", "category_slug": "album-releases", "limit": 20, "offset": 0, "debug": true }
```

Validation notes:

- if neither `category_id` nor `category_slug` is provided, the request is category browse/search mode
- `q` is allowed only in browse/search mode
- `category_id` and `category_slug` must not be sent together

## Action: `entries`

Return entries for a requested day, optionally narrowed by numeric `month` and `year`.

```json
{
  "action": "entries",
  "month": 9,
  "day": 24,
  "year": 1991,
  "filter_event_of_the_day": true,
  "filter_featured_event": true,
  "sort": "date_asc",
  "limit": 20,
  "offset": 0,
  "debug": true
}
```

Validation notes:

- `day` is required
- `month` is optional, but if present must be numeric `1` to `12`
- `year` is optional
- `filter_event_of_the_day` and `filter_featured_event` are optional booleans
- supported `sort` values are `date_desc` and `date_asc`

Filter semantics:

- no filters sent: all entries for the matched date
- `filter_event_of_the_day = true`: only entries where the source `default = 1`
- `filter_featured_event = true`: only entries where the source `eod = 1`
- both `true`: entries matching either flag

## Action: `timeline`

Browse or search timelines, or fetch entries for a specific timeline.

Browse/search example:

```json
{ "action": "timeline", "q": "nirvana", "limit": 20, "offset": 0, "debug": true }
```

Timeline entries by slug:

```json
{ "action": "timeline", "timeline_slug": "nirvana", "limit": 20, "offset": 0, "debug": true }
```

Validation notes:

- if neither `timeline_id` nor `timeline_slug` is provided, the request is timeline browse/search mode
- `q` is allowed only in browse/search mode
- `timeline_id` and `timeline_slug` must not be sent together

## Response excerpts

Category browse:

```json
{
  "meta": { "status": "ok", "matched": true },
  "categories": [
    {
      "id": 7,
      "name": "Album Releases",
      "slug": "album-releases",
      "entry_count": 142,
      "url": "https://calendar.songfacts.com/category/album-releases/"
    }
  ],
  "pagination": {
    "total": 127,
    "displayed": 20,
    "limit": 20,
    "offset": 0,
    "showall": false,
    "offset_out_of_range": false
  }
}
```

Entries by date:

```json
{
  "meta": { "status": "ok", "matched": true },
  "date": {
    "month": 9,
    "day": 24,
    "year": 1991
  },
  "entries": [
    {
      "id": 12345,
      "title": "Nirvana releases Nevermind",
      "description": "Short summary text",
      "body": "Main event body",
      "body_extended": "Extended event body",
      "has_extended_body": true,
      "event_of_the_day": true,
      "featured_event": false,
      "date": {
        "year": 1991,
        "month": 9,
        "day": 24
      },
      "extended_entry_url": "https://calendar.songfacts.com/september/24/12345",
      "main_image_url": "https://img.songfacts.com/calendar/12345.jpg"
    }
  ],
  "pagination": {
    "total": 2,
    "displayed": 2,
    "limit": 20,
    "offset": 0,
    "showall": false,
    "offset_out_of_range": false
  }
}
```

Timeline browse:

```json
{
  "meta": { "status": "ok", "matched": true },
  "timelines": [
    {
      "id": 3,
      "name": "Nirvana",
      "slug": "nirvana",
      "entry_count": 24,
      "url": "https://calendar.songfacts.com/timeline/nirvana/"
    }
  ],
  "pagination": {
    "total": 95,
    "displayed": 20,
    "limit": 20,
    "offset": 0,
    "showall": false,
    "offset_out_of_range": false
  }
}
```

Behavior notes:

- `event_of_the_day` maps from source `default = 1`
- `featured_event` maps from source `eod = 1`
- `extended_entry_url` is present only when an entry has both `title` and `description`
- `main_image_url` is present only when an entry has both `title` and `description`, and the shared calendar image exists
- related categories and timelines are returned only when mappings exist for that entry

## Response: failure

```json
{ "meta": { "status": "fail", "error": { "type": "request", "code": "invalid_fields" } } }
```

Common validation rules include:

- `showall` cannot be combined with `limit` or `offset`
- browse/search and id-or-slug lookup fields must not be mixed incorrectly
- `day` is required on `action = "entries"`
- month names such as `"September"` are invalid; use numeric month values

## Samples

- [cURL / bash](../../examples/curl.md)
- [JavaScript (Node.js)](../../examples/javascript.md)
- [Python](../../examples/python.md)
- [PHP](../../examples/php.md)
