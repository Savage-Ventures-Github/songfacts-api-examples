<?php
// Single-file, server-side web demo. Set the web document root to this
// public/ directory. The shared examples/.env is outside the public directory.
declare(strict_types=1);

function web_load_api_config(): array
{
    $configuredPath = getenv('SONGFACTS_ENV_FILE');
    $envPath = ($configuredPath !== false && $configuredPath !== '')
        ? $configuredPath
        : dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';

    if (!is_file($envPath) || !is_readable($envPath)) {
        throw new RuntimeException('API config file is missing or unreadable. Copy examples/.env.example to examples/.env and fill in your credentials.');
    }

    $fileValues = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if (!is_array($fileValues)) {
        throw new RuntimeException('Could not read the API config file. Check its KEY=value format.');
    }

    $config = [];
    foreach (['BASE_URL', 'CLIENT_ID', 'CLIENT_SECRET'] as $key) {
        $environmentValue = getenv($key);
        $config[$key] = ($environmentValue !== false && $environmentValue !== '')
            ? $environmentValue
            : (string)($fileValues[$key] ?? '');
    }

    if ($config['BASE_URL'] === '') {
        $config['BASE_URL'] = 'https://api.songfacts.com';
    }
    foreach (['CLIENT_ID', 'CLIENT_SECRET'] as $required) {
        if ($config[$required] === '') {
            throw new RuntimeException('CLIENT_ID and CLIENT_SECRET must be set in examples/.env or the hosting environment.');
        }
    }

    return $config;
}

function web_api_call(string $baseUrl, string $clientId, string $clientSecret, string $path, array $payload): array
{
    $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($body === false) {
        throw new RuntimeException('Could not encode the request as JSON.');
    }

    $timestamp = (string)time();
    $nonce = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    $bodyHash = hash('sha256', $body);
    $canonical = implode("\n", ['POST', $path, $timestamp, $nonce, $bodyHash]);
    $signature = hash_hmac('sha256', $canonical, $clientSecret);

    $handle = curl_init(rtrim($baseUrl, '/') . $path);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Client-Id: ' . $clientId,
            'X-Timestamp: ' . $timestamp,
            'X-Nonce: ' . $nonce,
            'X-Signature: ' . $signature,
        ],
    ]);

    $responseBody = curl_exec($handle);
    if ($responseBody === false) {
        $error = curl_error($handle);
        throw new RuntimeException('Could not reach the API: ' . $error);
    }
    $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    if ($status !== 200) {
        throw new RuntimeException('The API returned HTTP ' . $status . '.');
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('The API returned a response that was not valid JSON.');
    }
    return $decoded;
}

function web_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$artist = trim((string)($_POST['artist'] ?? ''));
$title = trim((string)($_POST['title'] ?? ''));
$result = null;
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if ($artist === '' || $title === '') {
        $error = 'Enter both an artist and a song title.';
    } else {
        try {
            $config = web_load_api_config();
            $result = web_api_call(
                $config['BASE_URL'],
                $config['CLIENT_ID'],
                $config['CLIENT_SECRET'],
                '/v5/songfacts',
                ['artist' => $artist, 'title' => $title, 'limit' => 5]
            );
        } catch (Throwable $exception) {
            // Do not display configuration values, signed headers, or secrets.
            $error = $exception->getMessage();
        }
    }
}
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Songfacts API Web Example</title>
  <style>
    body { max-width: 760px; margin: 3rem auto; padding: 0 1rem; font: 16px/1.5 system-ui, sans-serif; color: #24211f; }
    label { display: block; margin-top: 1rem; font-weight: 600; }
    input { box-sizing: border-box; width: 100%; padding: .65rem; margin-top: .25rem; }
    button { margin-top: 1rem; padding: .7rem 1rem; cursor: pointer; }
    pre { overflow-wrap: anywhere; white-space: pre-wrap; padding: 1rem; background: #f3efe9; }
    .error { color: #a21d16; }
  </style>
</head>
<body>
  <h1>Songfacts API example</h1>
  <p>This PHP page signs the request on the server. The shared secret is never sent to the browser.</p>
  <form method="post">
    <label for="artist">Artist</label>
    <input id="artist" name="artist" value="<?php echo web_h($artist); ?>" required>
    <label for="title">Song title</label>
    <input id="title" name="title" value="<?php echo web_h($title); ?>" required>
    <button type="submit">Find song facts</button>
  </form>

  <?php if ($error !== '') { ?>
    <p class="error" role="alert"><?php echo web_h($error); ?></p>
  <?php } ?>
  <?php if (is_array($result)) { ?>
    <h2>API response</h2>
    <pre><?php echo web_h((string)json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></pre>
  <?php } ?>
</body>
</html>
