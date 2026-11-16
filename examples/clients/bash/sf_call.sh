#!/usr/bin/env bash
# Sign and send one Songfacts API v5 JSON request.
# Usage: bash sf_call.sh /v5/songfacts '{"artist":"U2","title":"One"}'
set -euo pipefail

SCRIPT_DIR="$(CDPATH='' cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd)"
ENV_FILE="${SONGFACTS_ENV_FILE:-$SCRIPT_DIR/.env}"
if [[ ! -f "$ENV_FILE" ]]; then
  printf 'Config file not found: %s\nCopy examples/.env.example to examples/.env and fill in your credentials.\n' "$ENV_FILE" >&2
  exit 1
fi
# The .env file uses shell-compatible KEY=value lines. Keep it private and trusted.
# shellcheck disable=SC1090
source "$ENV_FILE"

: "${BASE_URL:=https://api.songfacts.com}"
: "${CLIENT_ID:?Set CLIENT_ID in examples/.env}"
: "${CLIENT_SECRET:?Set CLIENT_SECRET in examples/.env}"
API_PATH="${1:?Usage: bash sf_call.sh <path> <json-body>}"
BODY="${2:?Usage: bash sf_call.sh <path> <json-body>}"

TIMESTAMP="$(date +%s)"
NONCE="$(openssl rand -hex 16)"
BODY_HASH="$(printf '%s' "$BODY" | openssl dgst -sha256 | awk '{print $NF}')"
CANONICAL="$(printf '%s\n%s\n%s\n%s\n%s' POST "$API_PATH" "$TIMESTAMP" "$NONCE" "$BODY_HASH")"
SIGNATURE="$(printf '%s' "$CANONICAL" | openssl dgst -sha256 -mac HMAC -macopt "key:$CLIENT_SECRET" | awk '{print $NF}')"

curl -sS -X POST "${BASE_URL%/}${API_PATH}" \
  -H 'Content-Type: application/json' \
  -H "X-Client-Id: ${CLIENT_ID}" \
  -H "X-Timestamp: ${TIMESTAMP}" \
  -H "X-Nonce: ${NONCE}" \
  -H "X-Signature: ${SIGNATURE}" \
  --data-binary "$BODY"
