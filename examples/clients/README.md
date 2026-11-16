# Runnable API clients

These small server-side clients all load the shared configuration file at `examples/.env`. They also honor `BASE_URL`, `CLIENT_ID`, and `CLIENT_SECRET` already supplied by the process environment; environment values take precedence over the file.

| Client | File | Requirements |
| --- | --- | --- |
| Bash / cURL | [sf_call.sh](bash/sf_call.sh) | Bash, cURL, OpenSSL |
| Node.js | [songfacts_api_client.mjs](node/songfacts_api_client.mjs) | Node.js 18+; built-in modules only |
| Python | [songfacts_api_client.py](python/songfacts_api_client.py) | Python 3.8+; standard library only |
| PHP | [songfacts_api_client.php](php/songfacts_api_client.php) | PHP 7.4+ with cURL |

See the [examples setup guide](../README.md#setup) for creating the private config file and running each client.

The single-file [PHP web demo](../web/public/songfacts-example.php) is separate. Configure your web server's document root to `examples/web/public`; do not expose the repository root or the shared `.env` file. The demo signs API requests in PHP on the server and never sends the shared secret to the browser.
