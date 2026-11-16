<?php
// Server-side Songfacts API v5 client. Requires PHP 7.4+ and cURL.
// Loads examples/.env, two directories above this file. Process environment
// values take precedence over values in the file.

declare(strict_types=1);

function sf_load_example_env(): void
{
    $configuredPath = getenv('SONGFACTS_ENV_FILE');
    $envPath = ($configuredPath !== false && $configuredPath !== '')
        ? $configuredPath
        : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

    if (!is_file($envPath) || !is_readable($envPath)) {
        throw new RuntimeException(
            'Config file not found or unreadable: ' . $envPath .
            '. Copy examples/.env.example to examples/.env and fill in your credentials.'
        );
    }

    $values = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if (!is_array($values)) {
        throw new RuntimeException('Could not parse config file: ' . $envPath);
    }

    foreach ($values as $key => $value) {
        if (getenv((string)$key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

sf_load_example_env();

function sf_base_url(): string
{
    return rtrim((string)(getenv('BASE_URL') ?: 'https://api.songfacts.com'), '/');
}

function sf_nonce(): string
{
    return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
}

/** @param array<string,mixed> $payload @return array<string,mixed> */
function sf_call(string $path, array $payload, int $timeout = 10): array
{
    $clientId = getenv('CLIENT_ID');
    $clientSecret = getenv('CLIENT_SECRET');
    if ($clientId === false || $clientSecret === false || $clientId === '' || $clientSecret === '') {
        throw new RuntimeException('CLIENT_ID and CLIENT_SECRET must be set in examples/.env or the process environment');
    }

    $body = json_encode(
        $payload === [] ? new stdClass() : $payload,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
    if ($body === false) {
        throw new RuntimeException('Failed to encode request body');
    }

    $timestamp = (string)time();
    $nonce = sf_nonce();
    $bodyHash = hash('sha256', $body);
    $canonical = implode("\n", ['POST', $path, $timestamp, $nonce, $bodyHash]);
    $signature = hash_hmac('sha256', $canonical, $clientSecret);

    $handle = curl_init(sf_base_url() . $path);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
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
        $error = curl_error($handle);
        throw new RuntimeException('Transport error: ' . $error);
    }
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    if ($status !== 200) {
        throw new RuntimeException('HTTP ' . $status . ': ' . $raw);
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('API returned invalid JSON: ' . $raw);
    }
    return $decoded;
}

/** @param array<string,mixed> $payload @return array<string,mixed> */
function sf_songfacts(array $payload): array { return sf_call('/v5/songfacts', $payload); }
/** @param array<string,mixed> $payload @return array<string,mixed> */
function sf_artistfacts(array $payload): array { return sf_call('/v5/artistfacts', $payload); }
/** @param array<string,mixed> $payload @return array<string,mixed> */
function sf_categories(array $payload): array { return sf_call('/v5/categories', $payload); }
/** @param array<string,mixed> $payload @return array<string,mixed> */
function sf_blurbs(array $payload): array { return sf_call('/v5/blurbs', $payload); }
/** @param array<string,mixed> $payload @return array<string,mixed> */
function sf_calendar(array $payload): array { return sf_call('/v5/calendar', $payload); }

if (isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    $result = sf_songfacts(['id' => 933, 'limit' => 3]);
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), PHP_EOL;
}
