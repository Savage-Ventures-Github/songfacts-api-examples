# JavaScript Sample

A complete Songfacts API v5 client for Node.js. No dependencies — built-in `fetch` and `node:crypto`. **Node.js 18+.**

- [The client](#the-client)
- [The four core flows](#the-four-core-flows)
- [Reading the result](#reading-the-result)
- [Paging through facts](#paging-through-facts)
- [Verifying your signing](#verifying-your-signing)
- [Server-side only](#server-side-only)

Environment: `BASE_URL`, `CLIENT_ID`, `CLIENT_SECRET` — see [setup](README.md#setup).

---

## The client

`songfacts-client.mjs`

```js
// Minimal Songfacts API v5 client -- Node.js 18+, no dependencies.

import { createHash, createHmac, randomBytes } from "node:crypto";

const BASE_URL = (process.env.BASE_URL ?? "https://api.songfacts.com").replace(/\/+$/, "");
const CLIENT_ID = process.env.CLIENT_ID;
const CLIENT_SECRET = process.env.CLIENT_SECRET;

if (!CLIENT_ID || !CLIENT_SECRET) {
  throw new Error("CLIENT_ID and CLIENT_SECRET must be set");
}

/**
 * POST a signed JSON request to `path` (e.g. "/v5/songfacts").
 * Returns the parsed response body. The API answers HTTP 200 for both
 * success and failure, so inspect meta.status / meta.matched to branch.
 */
export async function call(path, payload, { timeoutMs = 10_000 } = {}) {
  // Serialize exactly once. This is the buffer we hash, sign, AND send --
  // if the bytes on the wire differ from the bytes we hashed, the signature
  // will not verify.
  const body = Buffer.from(JSON.stringify(payload), "utf8");

  const timestamp = String(Math.floor(Date.now() / 1000)); // whole seconds
  const nonce = randomBytes(24).toString("base64url");     // 32 chars from [A-Za-z0-9_-]
  const bodyHash = createHash("sha256").update(body).digest("hex");

  // Five lines, joined with \n: METHOD, PATH, TIMESTAMP, NONCE, BODY_HASH.
  const canonical = ["POST", path, timestamp, nonce, bodyHash].join("\n");

  const signature = createHmac("sha256", CLIENT_SECRET) // secret as issued -- do not base64-decode
    .update(canonical, "utf8")
    .digest("hex");                                      // lowercase hex, 64 chars

  const response = await fetch(BASE_URL + path, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Client-Id": CLIENT_ID,
      "X-Timestamp": timestamp,
      "X-Nonce": nonce,
      "X-Signature": signature,
    },
    body,                                                // the exact bytes we signed
    signal: AbortSignal.timeout(timeoutMs),
  });

  if (!response.ok) {
    // Non-200 is a transport or infrastructure problem, not an API verdict.
    throw new Error(`HTTP ${response.status}: ${await response.text()}`);
  }

  return response.json();
}

export const songfacts = (payload) => call("/v5/songfacts", payload);
export const artistfacts = (payload) => call("/v5/artistfacts", payload);
```

## The four core flows

`run-examples.mjs`

```js
import { artistfacts, songfacts } from "./songfacts-client.mjs";

const examples = [
  ["songfacts by id",     () => songfacts({ id: 933 })],
  ["songfacts by name",   () => songfacts({ artist: "U2", title: "One" })],
  ["artistfacts by id",   () => artistfacts({ id: 24 })],
  ["artistfacts by name", () => artistfacts({ artist: "Metallica" })],
];

for (const [label, run] of examples) {
  console.log("=".repeat(60));
  console.log(label);
  console.log("=".repeat(60));
  console.log(JSON.stringify(await run(), null, 2).slice(0, 1500));
}
```

```bash
node run-examples.mjs
```

## Reading the result

Branch on `meta.status` first, then `meta.matched` — never on the HTTP status.

```js
/** Returns { facts, attributionUrl } for a track, or null if unmatched. */
export async function factsForTrack(artist, title) {
  const result = await songfacts({ artist, title, limit: 5, debug: true });
  const meta = result.meta ?? {};

  if (meta.status === "fail") {
    const { type, code, fields } = meta.error;
    if (type === "internal") throw new RetryableError(code);        // retry with backoff
    if (type === "auth" || type === "access") {
      throw new ConfigurationError(code);                           // alert; do not retry
    }
    throw new BadRequestError(code, fields);                        // bug in the call
  }

  if (!meta.matched) {
    // Not an error. Log the diagnostics and collapse the module.
    console.info("no songfacts match", {
      normalizedInput: result.debug?.normalized_input,
      match: result.debug?.match,
      bestCandidate: result.best_candidate,
    });
    return null;
  }

  const song = result.song;

  // facts.items is keyed by fact ID; values are HTML; canonical order is
  // newest-first (descending fact ID). Sort explicitly rather than trusting
  // object key order.
  const facts = Object.entries(song.facts.items)
    .map(([id, html]) => ({ id: Number(id), html }))
    .sort((a, b) => b.id - a.id);

  return { facts, attributionUrl: song.url };
}
```

Notes:

- **`facts.items` values are HTML** — they contain inline `<i>` and `<b>`. Render as HTML (sanitize against your own allowlist if you're strict); there is no plain-text twin field.
- **Optional fields are omitted, not `null`.** Use optional chaining for `song.youtube?.url`, `song.lyrics?.writers`, `song.album`, `song.charts?.us`.
- **Cache `song.id`** against your own catalog ID, then call `songfacts({ id })` from then on — exact and immune to matcher variance.

## Paging through facts

```js
/** Yields every fact for a song, newest first. */
export async function* allFacts(songId, pageSize = 50) {
  let offset = 0;

  while (true) {
    const result = await call("/v5/songfacts", { id: songId, limit: pageSize, offset });
    const song = result.song;
    const page = song.facts?.items ?? {};   // omitted when offset is past the end

    for (const { id, html } of Object.entries(page)
      .map(([id, html]) => ({ id: Number(id), html }))
      .sort((a, b) => b.id - a.id)) {
      yield { id, html };
    }

    const { displayed, not_displayed: notDisplayed } = song.pagination;
    if (notDisplayed === 0 || displayed === 0) return;
    offset += displayed;
  }
}
```

Resolve once by name, then page by `id`. To fetch everything in a single call use `{ showall: true }` — with no `limit` or `offset` alongside it.

## Verifying your signing

Signing is deterministic, so you can validate it with no network call:

```js
import assert from "node:assert";
import { createHash, createHmac } from "node:crypto";

const secret = "test-secret";
const body = Buffer.from('{"artist":"U2","title":"One"}', "utf8");

const bodyHash = createHash("sha256").update(body).digest("hex");
const canonical = ["POST", "/v5/songfacts", "1718064000", "abc123_nonce_token", bodyHash].join("\n");
const signature = createHmac("sha256", secret).update(canonical, "utf8").digest("hex");

assert.equal(bodyHash, "1ade4d63aae82dcb1fe757a3ab942e06446631052ac01b795e38f2d3d38c14ec");
assert.equal(signature, "9ffdbed7075e2bf9ab81e1ae045f98f9d6750c11bd41d1532d712d9cd18a1773");
console.log("signing OK");
```

If those match, your canonical string, body hash, encoding and HMAC are all correct — any remaining failure is credentials or clock skew. If they don't, log `JSON.stringify(canonical)` and compare it against the [canonical string spec](../docs/authentication.md#the-canonical-string).

## Server-side only

Signed requests must be made from your server. Do not put `CLIENT_SECRET` in browser JavaScript, a mobile bundle, a serverless function whose source is public, or anywhere a client can read it — anything holding the secret can call the API as you.

If a browser or app needs facts, proxy through your own endpoint: your server signs the Songfacts call, caches the response, and returns just what your UI needs.

`fetch` on the edge: the same code runs unchanged on Workers, Deno and Bun with `node:crypto` support — or swap in Web Crypto (`crypto.subtle.importKey` / `sign` with `HMAC` + `SHA-256`, then hex-encode the result).

## Next reads

- [Authentication & Signing](../docs/authentication.md)
- [Error Handling](../docs/errors.md)
- [`/v5/songfacts`](../docs/endpoints/songfacts.md) · [`/v5/artistfacts`](../docs/endpoints/artistfacts.md)
