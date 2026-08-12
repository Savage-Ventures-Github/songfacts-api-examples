# PHP Sample

A complete Songfacts API v5 client in PHP. No dependencies beyond the cURL extension. **PHP 7.4+.**

- [The client](#the-client)
- [The four core flows](#the-four-core-flows)
- [Reading the result](#reading-the-result)
- [Paging through facts](#paging-through-facts)
- [Verifying your signing](#verifying-your-signing)

Environment: `BASE_URL`, `CLIENT_ID`, `CLIENT_SECRET` — see [setup](README.md#setup).

---

## The client

`songfacts_client.php`

```php
<?php
// Minimal Songfacts API v5 client -- cURL extension only.

declare(strict_types=1);

function sf_base_url(): string
{
    return rtrim(getenv('BASE_URL') ?: 'https://api.songfacts.com', '/');
}

function sf_nonce(): string
{
    // 32 chars from [A-Za-z0-9_-] -- base64url of 24 random bytes, unpadded.
    return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
}

/**
 * POST a signed JSON request to $path (e.g. "/v5/songfacts").
 *
 * Returns the decoded response body as an array. The API answers HTTP 200 for
 * both success and failure, so inspect meta.status / meta.matched to branch.
 *
 * @param array<string,mixed> $payload
 * @return array<string,mixed>
 */
function sf_call(string $path, array $payload, int $timeout = 10): array
{
    $clientId = getenv('CLIENT_ID');
    $clientSecret = getenv('CLIENT_SECRET');
    if ($clientId === false || $clientSecret === false) {
        throw new RuntimeException('CLIENT_ID and CLIENT_SECRET must be set');
    }

    // Encode exactly once. This is the string we hash, sign, AND send -- if the
    // bytes on the wire differ from the bytes we hashed, the signature will not
    // verify. An empty payload must encode as "{}", not "[]".
    $body = json_encode(
        $payload === [] ? new stdClass() : $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if ($body === false) {
        throw new RuntimeException('failed to encode request body');
    }

    $timestamp = (string) time();          // whole seconds
    $nonce = sf_nonce();
    $bodyHash = hash('sha256', $body);     // lowercase hex

    // Five lines, joined with \n: METHOD, PATH, TIMESTAMP, NONCE, BODY_HASH.
    $canonical = implode("\n", ['POST', $path, $timestamp, $nonce, $bodyHash]);

    // Secret as issued -- do not base64-decode it first.
    $signature = hash_hmac('sha256', $canonical, $clientSecret); // lowercase hex, 64 chars

    $handle = curl_init(sf_base_url() . $path);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,       // the exact bytes we signed
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Client-Id: ' . $clientId,
            'X-Timestamp: ' . $timestamp,
            'X-Nonce: ' . $nonce,
            'X-Signature: ' . $signature,
        ],
    ]);

    $raw = curl_exec($handle);
    if ($raw === false) {
        throw new RuntimeException('transport error: ' . curl_error($handle));
    }

    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

    if ($status !== 200) {
        // Non-200 is a transport or infrastructure problem, not an API verdict.
        throw new RuntimeException("HTTP {$status}: {$raw}");
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('unparseable response body: ' . $raw);
    }

    return $decoded;
}

/** @param array<string,mixed> $payload @return array<string,mixed> */
function sf_songfacts(array $payload): array
{
    return sf_call('/v5/songfacts', $payload);
}

/** @param array<string,mixed> $payload @return array<string,mixed> */
function sf_artistfacts(array $payload): array
{
    return sf_call('/v5/artistfacts', $payload);
}
```

Two PHP-specific notes:

- **`json_encode` and empty arrays.** An empty PHP array encodes as `[]`, which is not a valid request body — the client above converts it to `{}`. Be careful with integer-keyed arrays generally; build payloads as explicit string-keyed maps such as `['artist' => 'U2', 'title' => 'One']`.
- **No `curl_close()`.** It's deprecated as of PHP 8.5 and has had no effect since 8.0. The handle is released when it goes out of scope.

## The four core flows

`run_examples.php`

```php
<?php

declare(strict_types=1);

require __DIR__ . '/songfacts_client.php';

$examples = [
    'songfacts by id'     => fn(): array => sf_songfacts(['id' => 933]),
    'songfacts by name'   => fn(): array => sf_songfacts(['artist' => 'U2', 'title' => 'One']),
    'artistfacts by id'   => fn(): array => sf_artistfacts(['id' => 24]),
    'artistfacts by name' => fn(): array => sf_artistfacts(['artist' => 'Metallica']),
];

foreach ($examples as $label => $run) {
    echo str_repeat('=', 60), PHP_EOL, $label, PHP_EOL, str_repeat('=', 60), PHP_EOL;
    echo substr((string) json_encode($run(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 0, 1500), PHP_EOL;
}
```

```bash
php run_examples.php
```

## Reading the result

Branch on `meta.status` first, then `meta.matched` — never on the HTTP status.

```php
/**
 * Returns ['facts' => [factId => html, ...], 'url' => string] for a track,
 * or null when nothing matched.
 */
function sf_facts_for_track(string $artist, string $title): ?array
{
    $result = sf_songfacts([
        'artist' => $artist,
        'title' => $title,
        'limit' => 5,
        'debug' => true,
    ]);

    $meta = $result['meta'] ?? [];

    if (($meta['status'] ?? null) === 'fail') {
        $error = $meta['error'];
        if ($error['type'] === 'internal') {
            throw new SfRetryableError($error['code']);      // retry with backoff
        }
        if (in_array($error['type'], ['auth', 'access'], true)) {
            throw new SfConfigurationError($error['code']);  // alert; do not retry
        }
        throw new SfBadRequestError($error['code'], $error['fields'] ?? []);
    }

    if (empty($meta['matched'])) {
        // Not an error. Log the diagnostics and collapse the module.
        error_log('no songfacts match: ' . json_encode([
            'normalized_input' => $result['debug']['normalized_input'] ?? null,
            'match' => $result['debug']['match'] ?? null,
            'best_candidate' => $result['best_candidate'] ?? null,
        ]));
        return null;
    }

    $song = $result['song'];

    // facts.items is keyed by fact ID; values are HTML; canonical order is
    // newest-first (descending fact ID). Sort explicitly.
    $facts = $song['facts']['items'] ?? [];
    krsort($facts, SORT_NUMERIC);

    return ['facts' => $facts, 'url' => $song['url']];
}
```

Notes:

- **`facts.items` values are HTML** — they contain inline `<i>` and `<b>`. Echo them as HTML (do not `htmlspecialchars()` them, or the markup will show as literal text); sanitize against your own allowlist if you're strict. There is no plain-text twin field.
- **Optional fields are omitted, not `null`.** Use `isset()` / `??` for `youtube`, `lyrics`, `album`, `charts`.
- **Cache `$song['id']`** against your own catalog ID, then use `sf_songfacts(['id' => $id])` from then on — exact and immune to matcher variance.

## Paging through facts

```php
/**
 * Returns every fact for a song, newest first, as [factId => html].
 * @return array<int,string>
 */
function sf_all_facts(int $songId, int $pageSize = 50): array
{
    $all = [];
    $offset = 0;

    while (true) {
        $result = sf_songfacts(['id' => $songId, 'limit' => $pageSize, 'offset' => $offset]);
        $song = $result['song'];

        // 'facts' is omitted when offset is past the end of the set.
        foreach ($song['facts']['items'] ?? [] as $factId => $html) {
            $all[(int) $factId] = $html;
        }

        $pagination = $song['pagination'];
        if ($pagination['not_displayed'] === 0 || $pagination['displayed'] === 0) {
            break;
        }
        $offset += $pagination['displayed'];
    }

    krsort($all, SORT_NUMERIC);

    return $all;
}
```

Resolve once by name, then page by `id`. To fetch everything in a single call use `['showall' => true]` — with no `limit` or `offset` alongside it.

## Verifying your signing

Signing is deterministic, so you can validate it with no network call:

```php
<?php

$secret = 'test-secret';
$body = '{"artist":"U2","title":"One"}';

$bodyHash = hash('sha256', $body);
$canonical = implode("\n", ['POST', '/v5/songfacts', '1718064000', 'abc123_nonce_token', $bodyHash]);
$signature = hash_hmac('sha256', $canonical, $secret);

assert($bodyHash === '1ade4d63aae82dcb1fe757a3ab942e06446631052ac01b795e38f2d3d38c14ec');
assert($signature === '9ffdbed7075e2bf9ab81e1ae045f98f9d6750c11bd41d1532d712d9cd18a1773');
echo 'signing OK', PHP_EOL;
```

If those match, your canonical string, body hash, encoding and HMAC are all correct — any remaining failure is credentials or clock skew. If they don't, `var_dump($canonical)` and compare it against the [canonical string spec](../docs/authentication.md#the-canonical-string).

## Next reads

- [Authentication & Signing](../docs/authentication.md)
- [Error Handling](../docs/errors.md)
- [`/v5/songfacts`](../docs/endpoints/songfacts.md) · [`/v5/artistfacts`](../docs/endpoints/artistfacts.md)
