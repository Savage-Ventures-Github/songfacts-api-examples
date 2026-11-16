// Server-side Songfacts API v5 client for Node.js 18+; no npm dependencies.
// This client loads examples/.env, two directories above this file.
import { createHash, createHmac, randomBytes } from "node:crypto";
import { readFileSync } from "node:fs";
import { fileURLToPath, pathToFileURL } from "node:url";
import path from "node:path";

function loadExampleEnv() {
  const envPath = process.env.SONGFACTS_ENV_FILE
    ? path.resolve(process.env.SONGFACTS_ENV_FILE)
    : path.resolve(path.dirname(fileURLToPath(import.meta.url)), "../../.env");
  let content;
  try {
    content = readFileSync(envPath, "utf8");
  } catch {
    throw new Error(`Config file not found: ${envPath}. Copy examples/.env.example to examples/.env and fill in your credentials.`);
  }

  for (const rawLine of content.split(/\r?\n/)) {
    const line = rawLine.trim();
    if (!line || line.startsWith("#")) continue;
    const separator = line.indexOf("=");
    if (separator < 1) continue;
    const key = line.slice(0, separator).trim();
    const value = line.slice(separator + 1).trim();
    if (!Object.hasOwn(process.env, key)) process.env[key] = value;
  }
}

loadExampleEnv();

const BASE_URL = (process.env.BASE_URL ?? "https://api.songfacts.com").replace(/\/+$/, "");
const CLIENT_ID = process.env.CLIENT_ID;
const CLIENT_SECRET = process.env.CLIENT_SECRET;
if (!CLIENT_ID || !CLIENT_SECRET) {
  throw new Error("CLIENT_ID and CLIENT_SECRET must be set in examples/.env or the process environment");
}

export async function call(path, payload, { timeoutMs = 10_000 } = {}) {
  const body = Buffer.from(JSON.stringify(payload), "utf8");
  const timestamp = String(Math.floor(Date.now() / 1000));
  const nonce = randomBytes(24).toString("base64url");
  const bodyHash = createHash("sha256").update(body).digest("hex");
  const canonical = ["POST", path, timestamp, nonce, bodyHash].join("\n");
  const signature = createHmac("sha256", CLIENT_SECRET).update(canonical, "utf8").digest("hex");

  const response = await fetch(BASE_URL + path, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "X-Client-Id": CLIENT_ID,
      "X-Timestamp": timestamp,
      "X-Nonce": nonce,
      "X-Signature": signature,
    },
    body,
    signal: AbortSignal.timeout(timeoutMs),
  });
  const raw = await response.text();
  if (!response.ok) throw new Error(`HTTP ${response.status}: ${raw}`);
  try {
    return JSON.parse(raw);
  } catch {
    throw new Error(`API returned invalid JSON: ${raw}`);
  }
}

export const songfacts = (payload) => call("/v5/songfacts", payload);
export const artistfacts = (payload) => call("/v5/artistfacts", payload);
export const categories = (payload) => call("/v5/categories", payload);
export const blurbs = (payload) => call("/v5/blurbs", payload);
export const calendar = (payload) => call("/v5/calendar", payload);

if (process.argv[1] && import.meta.url === pathToFileURL(path.resolve(process.argv[1])).href) {
  songfacts({ id: 933, limit: 3 })
    .then((result) => console.log(JSON.stringify(result, null, 2)))
    .catch((error) => {
      console.error(error.message);
      process.exitCode = 1;
    });
}
