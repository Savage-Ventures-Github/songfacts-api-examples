"""Server-side Songfacts API v5 client; Python 3.8+, standard library only.

Loads examples/.env, two directories above this file. Existing process
environment variables take precedence over values in the file.
"""

import hashlib
import hmac
import json
import os
import secrets
import time
import urllib.error
import urllib.request
from pathlib import Path


def _load_example_env():
    configured_path = os.environ.get("SONGFACTS_ENV_FILE")
    env_path = Path(configured_path).expanduser() if configured_path else Path(__file__).resolve().parents[2] / ".env"
    try:
        lines = env_path.read_text(encoding="utf-8").splitlines()
    except OSError as exc:
        raise RuntimeError(
            f"Config file not found: {env_path}. Copy examples/.env.example to examples/.env and fill in your credentials."
        ) from exc

    for raw_line in lines:
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        key = key.strip()
        if key and key not in os.environ:
            os.environ[key] = value.strip()


_load_example_env()
BASE_URL = os.environ.get("BASE_URL", "https://api.songfacts.com").rstrip("/")
CLIENT_ID = os.environ.get("CLIENT_ID")
CLIENT_SECRET = os.environ.get("CLIENT_SECRET")
if not CLIENT_ID or not CLIENT_SECRET:
    raise RuntimeError("CLIENT_ID and CLIENT_SECRET must be set in examples/.env or the process environment")


def call(path, payload, timeout=10):
    """POST a signed request and return the decoded JSON response."""
    body = json.dumps(payload, separators=(",", ":"), ensure_ascii=False).encode("utf-8")
    timestamp = str(int(time.time()))
    nonce = secrets.token_urlsafe(24)
    body_hash = hashlib.sha256(body).hexdigest()
    canonical = "\n".join(("POST", path, timestamp, nonce, body_hash))
    signature = hmac.new(CLIENT_SECRET.encode("utf-8"), canonical.encode("utf-8"), hashlib.sha256).hexdigest()

    request = urllib.request.Request(
        BASE_URL + path,
        data=body,
        method="POST",
        headers={
            "Content-Type": "application/json",
            "X-Client-Id": CLIENT_ID,
            "X-Timestamp": timestamp,
            "X-Nonce": nonce,
            "X-Signature": signature,
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=timeout) as response:
            raw = response.read().decode("utf-8")
    except urllib.error.HTTPError as exc:
        raw = exc.read().decode("utf-8", errors="replace")
        if exc.code != 200:
            raise RuntimeError(f"HTTP {exc.code}: {raw}") from exc
    except urllib.error.URLError as exc:
        raise RuntimeError(f"Transport error: {exc.reason}") from exc
    return json.loads(raw)


def songfacts(payload):
    return call("/v5/songfacts", payload)


def artistfacts(payload):
    return call("/v5/artistfacts", payload)


def categories(payload):
    return call("/v5/categories", payload)


def blurbs(payload):
    return call("/v5/blurbs", payload)


def calendar(payload):
    return call("/v5/calendar", payload)


if __name__ == "__main__":
    print(json.dumps(songfacts({"id": 933, "limit": 3}), ensure_ascii=False, indent=2))
