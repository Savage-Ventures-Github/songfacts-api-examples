# Code Samples

Signed, runnable examples for Songfacts API v5 in four languages. Each one implements the same thing: build the canonical string, sign it, send a JSON `POST`, and inspect the result. The samples cover both the original songfacts and artistfacts flows and the current v1.1 endpoint families for categories, blurbs, and calendar.

| Language | File | Requires |
| --- | --- | --- |
| cURL / bash | [clients/bash/sf_call.sh](clients/bash/sf_call.sh) | `bash`, `curl`, `openssl` |
| JavaScript | [clients/node/songfacts_api_client.mjs](clients/node/songfacts_api_client.mjs) | Node.js 18+ (built-in `fetch` and `node:crypto`) |
| Python | [clients/python/songfacts_api_client.py](clients/python/songfacts_api_client.py) | Python 3.8+ (standard library only) |
| PHP | [clients/php/songfacts_api_client.php](clients/php/songfacts_api_client.php) | PHP 7.4+ with cURL |
| PHP web demo | [web/public/songfacts-example.php](web/public/songfacts-example.php) | PHP 7.4+ with cURL and a web server |

No SDK is required — signing is about twenty lines in any language.

The runnable files and shared config are arranged like this:

```text
examples/
├── .env.example                         # safe template, committed
├── .env                                 # your private copy, ignored by Git
├── .gitignore
├── clients/                             # server-side clients; not web-served
│   ├── bash/sf_call.sh
│   ├── node/songfacts_api_client.mjs
│   ├── python/songfacts_api_client.py
│   └── php/songfacts_api_client.php
└── web/
	└── public/                          # web server document root for demo
		└── songfacts-example.php         # one-file PHP web example
```

## Setup: save your settings for reuse

The runnable clients and PHP web demo read one private config file shared by every language. The instructions below use paths relative to the repository root (the folder containing the top-level `README.md`). Start there; do not `cd` into `examples/` before running these commands.

1. The tracked template is `examples/.env.example`.
2. **Copy** the template to `examples/.env`. Do not rename or edit `.env.example`; leave it in place as a clean template for future setup.

   macOS, Linux, or Git Bash:

   ```bash
   cp examples/.env.example examples/.env
   ```

   Windows PowerShell:

   ```powershell
   Copy-Item examples/.env.example examples/.env
   ```

   Windows Command Prompt:

   ```bat
   copy examples\.env.example examples\.env
   ```

3. Open `examples/.env` in a text editor and replace the example values with the credentials issued to you:

   ```text
   BASE_URL=https://api.songfacts.com
   CLIENT_ID=your-client-id
   CLIENT_SECRET=your-shared-secret
   ```

4. Save the file. Use one `NAME=value` setting per line, with no quotes or spaces around `=`. The client programs look for `examples/.env` relative to their own files. They also accept these values from the process environment; environment values take precedence over values in the file.
5. From the repository root, run the client for your language. For example:

   ```bash
   bash examples/clients/bash/sf_call.sh /v5/songfacts '{"artist":"U2","title":"One"}'
   ```

   ```bash
   node examples/clients/node/songfacts_api_client.mjs
   python examples/clients/python/songfacts_api_client.py
   php examples/clients/php/songfacts_api_client.php
   ```

   The Node.js, Python, and PHP files provide functions for calling each endpoint. See the language guides for examples of using those functions in your application.

`BASE_URL` must be the API host assigned to your integration: production, or a staging/dev host provided for your account. Credentials come from Songfacts; see [Get Access](../README.md#get-access-to-songfacts-api).

The repository includes `examples/.gitignore`, which excludes the private `examples/.env` file from Git. The example template `.env.example` is safe to commit because it contains only placeholders. Still, treat `.env` as a password: do not commit it, upload it to a public web directory, or put it in screenshots or support messages.

### Using the PHP web demo

The single-file web example is `examples/web/public/songfacts-example.php`. For a quick local preview from the repository root, run:

```bash
php -S 127.0.0.1:8000 -t examples/web/public
```

Then open `http://127.0.0.1:8000/songfacts-example.php`. This built-in PHP server is for local development only. When configuring a real web server, set its **document root** (the directory it serves to visitors) to `examples/web/public/`—not to the repository root or the `examples/` directory. The private config file is `examples/.env`, outside that public directory.

The demo signs requests in PHP on the server. It does not put `CLIENT_SECRET` in HTML or browser JavaScript. The example form is not an authentication system; protect a deployed demo or restrict it to a development environment so visitors cannot use your API quota.

For a real production website, prefer your hosting provider's secret/environment settings or a secrets manager. If you use a config file, keep it outside the public document root and restrict read access to the application account. Do not deploy this repository's example `.env` with real credentials.

The Markdown guides also contain compact code snippets for learning and adapting the signing flow. Those snippets read environment variables; the runnable files under `examples/clients/` additionally load the shared `examples/.env` file.

## The core flows

Each sample covers the four foundational lookups:

| Flow | Endpoint | Body |
| --- | --- | --- |
| Songfacts by ID | `/v5/songfacts` | `{"id": 933}` |
| Songfacts by name | `/v5/songfacts` | `{"artist": "U2", "title": "One"}` |
| Artistfacts by ID | `/v5/artistfacts` | `{"id": 24}` |
| Artistfacts by name | `/v5/artistfacts` | `{"artist": "Metallica"}` |

## The v1.1 endpoint flows

Each sample also includes runnable request bodies for the newer endpoint families:

| Flow | Endpoint | Body |
| --- | --- | --- |
| Categories list/search | `/v5/categories` | `{"action": "list", "q": "ryan tedder", "sort": "relevance", "limit": 10}` |
| Category songs by slug | `/v5/categories` | `{"action": "songs", "category_slug": "songs-written-by-ryan-tedder", "artist": "OneRepublic", "sort": "chart_position", "limit": 10}` |
| Blurbs by artist | `/v5/blurbs` | `{"artist": "Taylor Swift"}` |
| Blurbs by artist + title | `/v5/blurbs` | `{"artist": "U2", "title": "One"}` |
| Calendar categories browse/search | `/v5/calendar` | `{"action": "categories", "q": "album", "limit": 20}` |
| Calendar timeline by slug | `/v5/calendar` | `{"action": "timeline", "timeline_slug": "nirvana", "limit": 20}` |
| Calendar entries by date | `/v5/calendar` | `{"action": "entries", "month": 9, "day": 24, "year": 1991, "limit": 20}` |

## The signing recipe

Identical in every language:

```text
body      = the exact JSON bytes you will send
body_hash = lowercase_hex(sha256(body))
canonical = "POST" + "\n" + path + "\n" + timestamp + "\n" + nonce + "\n" + body_hash
signature = lowercase_hex(hmac_sha256(key = client_secret, message = canonical))
```

Then send:

```text
Content-Type: application/json
X-Client-Id:  <client_id>
X-Timestamp:  <timestamp>
X-Nonce:      <nonce>
X-Signature:  <signature>
```

Three rules that account for nearly every `bad_signature`:

1. **Serialize the body once.** Hash that exact buffer, sign it, and send that same buffer. Every sample below does this deliberately — don't let an HTTP client re-serialize your JSON after you've signed it.
2. **Sign the path only** — `/v5/songfacts`, not the full URL, no query string, no trailing slash.
3. **Fresh nonce and timestamp on every request**, including retries.

Full rules: [Authentication & Signing](../docs/authentication.md).

## Verifying your implementation

Signing is deterministic, so you can check your code without making a network call. With:

```text
secret    = test-secret
method    = POST
path      = /v5/songfacts
timestamp = 1718064000
nonce     = abc123_nonce_token
body      = {"artist":"U2","title":"One"}
```

you must get:

```text
body_hash = 1ade4d63aae82dcb1fe757a3ab942e06446631052ac01b795e38f2d3d38c14ec
signature = 9ffdbed7075e2bf9ab81e1ae045f98f9d6750c11bd41d1532d712d9cd18a1773
```

> These reference digests were generated from the samples in this folder. Compute them with your own implementation using the inputs above — if both values match, your canonical string, body hash, encoding and HMAC are all correct, and any remaining failure is credentials or clock. If they don't match, print `repr(canonical)` and compare it line by line against the [canonical string spec](../docs/authentication.md#the-canonical-string).

## What to expect back

| Outcome | Body |
| --- | --- |
| Match | `meta.status = "ok"`, `meta.matched = true`, plus endpoint-specific payload objects |
| No match | `meta.status = "ok"`, `meta.matched = false` — a successful call, not an error |
| Auth failure | `meta.status = "fail"`, `meta.error.type = "auth"` |
| Validation failure | `meta.status = "fail"`, `meta.error.code = "invalid_fields"` |

**The HTTP status is always `200`.** Branch on `meta.status`, then `meta.matched`. For `songfacts` and `artistfacts`, matched data comes back under `song` or `artist`. The newer endpoints return endpoint-specific payloads such as `categories`, `songs`, `entries`, `category`, `timeline`, or a matched object with a single `blurb`. See [Error Handling](../docs/errors.md).

Add `"debug": true` to any body while integrating — the root `debug` object shows the normalized input the matcher actually used, the method it chose, and the score against the threshold.
