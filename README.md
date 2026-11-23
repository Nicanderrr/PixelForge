# Forma — an image generation studio

Forma is a Laravel, Vue, and Inertia application built for the PromptEdit Senior Product Engineer assessment. Users register, describe an image, receive a live cost estimate, submit an asynchronous PixelForge generation, and see its status, image, and exact finalized charge.

**Provider:** [PixelForge API documentation](https://pixelforge.promptedit.dev/docs) · [Assessment](https://pixelforge.promptedit.dev/assessment)

PixelForge itself is simulated: its images are abstract placeholders and there are no real payments. Forma calls the actual documented service. Production code has no fake image generator or fallback provider. HTTP fakes are confined to automated tests.

## Current status

- Implemented: registration, session login/logout, account settings, dashboard, creation, history, detail pages, user isolation, live estimates, exact accounting, queues, signed webhook inbox, polling recovery, and provider usage reconciliation.
- Verified locally: PHPUnit against isolated MariaDB, a production TypeScript/Vite build, Chrome registration/login/profile flow, mobile layout, and real unauthenticated `GET /v1/pricing`.
- Real authenticated generation, provider-delivered webhooks, and real usage reconciliation require a development API key. They have **not** been verified without it.
- Public deployment and GitHub publication require hosting/GitHub access and have not been completed.
- Bonus admin reporting and wallets are intentionally deferred until live core validation is complete. There is no real payment collection; “charge” means an immutable accounting record.

See [validation notes](docs/VALIDATION.md) for evidence and limitations, [API implementation decisions](docs/API-PLAN.md), and [interview preparation](docs/INTERVIEW.md).

## Stack

- PHP 8.3+, Laravel 13, Laravel HTTP Client and database queues
- Vue 3, Inertia 2, TypeScript, Tailwind CSS 4, Vite 7
- MySQL 8.4 recommended for deployment; local isolated MariaDB 11.4.14 (MySQL protocol/InnoDB)
- BCMath for decimal arithmetic; SQL `DECIMAL(30,18)` for USD
- PHPUnit 12 and Playwright; real multi-process InnoDB concurrency test

Exact dependency versions are pinned in `composer.lock` and `package-lock.json`.

## This machine: run on port 8030

Project directory: `C:\xampp\htdocs\promptedit-pixelforge`.

The local `.env` is already configured, including the generated Laravel key and unique database credentials. It is ignored by Git. The new database runs on **127.0.0.1:3308**, uses databases `forma` and `forma_test`, and has its files and binaries under the ignored `.runtime` directory. Existing XAMPP databases and applications are untouched.

1. Open `.env` and fill in `PIXELFORGE_API_KEY=` with your dedicated development key.
2. For webhooks, also fill in `PIXELFORGE_WEBHOOK_SECRET=` with that key’s signing secret (not an invented value).
3. Open **http://127.0.0.1:8030** and register your own account.

The app, queue listener, and scheduler can be started from PowerShell:

```powershell
cd C:\xampp\htdocs\promptedit-pixelforge
powershell -ExecutionPolicy Bypass -File scripts\start-local.ps1
```

The launcher refuses to replace a process already listening on 8030. Logs and process IDs are in `.runtime`. Local `queue:listen` starts each job with fresh configuration, so a newly pasted API key is picked up without a long-lived worker restart. Refresh the Create Image page after setting the key. Avoid `config:cache` during local development.

Browser smoke testing creates test-only local accounts with addresses beginning `browser-` at `example.test`. No production users or sample generations are seeded.

## Fresh installation on another machine

Requirements: PHP 8.3 with BCMath, cURL, mbstring, OpenSSL, PDO MySQL, DOM/XML, fileinfo and zip; Composer 2; Node 24; MySQL 8.4 or supported MariaDB. This project targets MySQL-compatible databases; it does not claim PostgreSQL portability for its immutable-ledger triggers.

```sh
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Create two **new, isolated** UTF-8 databases, `forma` and `forma_test`, and a local user with permission on them. Set the `DB_*` values in `.env`. Do not point the test database at a database containing other work.

```sh
php artisan migrate
npm run build
php artisan serve --host=127.0.0.1 --port=8030
```

In separate terminals:

```sh
php artisan queue:listen --sleep=1 --tries=3 --timeout=60
php artisan schedule:work
```

For frontend hot reload use `npm run dev`; keep the Laravel server on 8030. Use compiled assets for the simplest local verification.

### Environment variables

| Variable | Purpose |
| --- | --- |
| `APP_KEY` | Generated application/session encryption key |
| `APP_URL` | `http://127.0.0.1:8030` locally; actual HTTPS origin in production |
| `APP_ENV`, `APP_DEBUG` | `production` and `false` when deployed |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Dedicated MySQL-compatible database |
| `QUEUE_CONNECTION=database` | Durable jobs; never use `sync` in a deployed app |
| `CACHE_STORE=database` | Shared account admission lock, quota budget, unique-job locks, and pricing cache |
| `CACHE_PREFIX` | Same across nodes of one deployment, distinct from unrelated apps |
| `SESSION_DRIVER=database`, `SESSION_ENCRYPT=true` | Server sessions |
| `SESSION_SECURE_COOKIE` | `false` for localhost HTTP, `true` for production HTTPS |
| `PIXELFORGE_API_KEY` | Bearer key, server only; use a different key per environment |
| `PIXELFORGE_WEBHOOK_SECRET` | That key’s documented signing secret |
| `PIXELFORGE_BASE_URL` | Defaults to `https://pixelforge.promptedit.dev/v1` |
| `PIXELFORGE_ACCOUNT_SCOPE` | Shared limiter/lock namespace for one provider account |

Never prefix a secret with `VITE_`, put it in a URL, or commit `.env`. The frontend receives only a boolean indicating whether the provider is configured.

## Provider lifecycle and recovery

1. `GenerationRequest` validates the form. `RequestGeneration` obtains live pricing, locks the user, and persists an immutable provider request payload and UUID.
2. The UUID is the permanent provider `Idempotency-Key`; `metadata.local_id` correlates early webhooks. A separate unique `(user_id, request_key)` prevents browser double-submits.
3. `AdvanceGeneration` serializes account admission with a shared cache lock. It reserves at most three local provider slots and budgets 50 authenticated requests/minute, leaving headroom below the provider’s 60/minute limit.
4. HTTP happens **outside** database transactions, with 5-second connect and 20-second overall timeouts. Each job performs one request. No automatic HTTP-client retry can secretly create a second POST.
5. Webhooks and polling both call `FinalizeGeneration`. It locks the generation, validates identity and token data, applies monotonic state changes, and inserts the charge in the same transaction as success.
6. The scheduler scans durable due records every ten seconds. Accepted jobs are normally polled after 15 seconds when callbacks have not finalized them. A worker crash after sending POST is recovered using the original key and stored payload.

States: `queued` → `submitting` → provider `queued` / `processing` → `succeeded` / `failed`. `attention` means automation stopped with an unresolved outcome, never a claim of free failure. A later valid terminal webhook can resolve `attention`.

429 rate/concurrency limits and 503 overloads respect `Retry-After`, with exponential backoff plus 0–5 seconds of jitter. There are at most eight consecutive automatic errors. Documented content-policy, parameter, and daily-quota create rejections are terminal free failures. Authentication/identity errors stop for operator intervention. Network ambiguity never becomes a false “failed, free” outcome.

Retries reserve their concurrency slot conservatively, including ambiguous exhausted requests. This can reduce throughput; operators must investigate attention records rather than blindly releasing capacity. Other apps or environments on the same provider account can consume slots; provider 429 responses remain authoritative.

The 24-hour automated recovery window is bounded. An operator can explicitly extend it without changing the request identity:

```sh
php artisan pixelforge:recover
php artisan pixelforge:retry GENERATION_UUID
php artisan queue:failed
```

`pixelforge:retry` refuses terminal requests and checks the original key fingerprint. Restore the original API key before recovering an old request after credential rotation. For a genuinely new image after a terminal failure, create a new request from the UI.

## Exact pricing and the ledger

Rates are loaded from unauthenticated `GET /v1/pricing` and cached for five minutes. The estimator uses Unicode character count, documented reference/output token rules, and the midpoint of the reasoning-token range. The upper estimate uses maximum reasoning and a ceiling on the documented text variance. It is an estimate based on current pricing, not a binding cap or wallet reservation.

Final cost uses the four actual usage categories and the **rates embedded in that generation**, which are fixed by PixelForge at creation:

```text
provider USD = Σ(actual category tokens × generation category rate / 1,000,000)
customer USD = provider USD × 1.25
```

`Money` performs BCMath operations on strings only. Scale 18 accommodates rates with up to nine decimal places, division by one million, and the exact 25% markup. Unsupported rate precision is rejected instead of silently rounded. No financial PHP float arithmetic is used. The frontend uses `BigInt` for formatting financial strings.

Accounting amounts are never rounded. UI summaries round half up to six decimal places; detail pages expose exact stored amounts. Each successful generation inserts one `generation_charges` row with its usage, rates, provider cost, customer charge, owner, and UTC completion timestamp. A unique generation foreign key prevents duplicate charges. SQL triggers reject updates and deletes, so a future correction must be a separate adjustment design, not an overwrite. Failed generations store zero final amounts and have no charge row.

## Webhook configuration and security

Configure the development key in the PixelForge dashboard with a publicly reachable tunnel URL:

```text
https://YOUR-PUBLIC-TUNNEL/webhooks/pixelforge
```

Forward the tunnel to port 8030. PixelForge rejects private/localhost webhook URLs. Copy the signing secret for **that same API key** to `.env`. Polling still works without a tunnel; it does not prove webhook delivery works.

The route is excluded from CSRF protection. Before JSON decoding, it verifies `Pixelforge-Signature: t=...,v1=...` as hex HMAC-SHA256 over `timestamp.raw_body`, uses `hash_equals`, and requires the timestamp within ±300 seconds. Blank secrets fail closed. Request size, envelope, status/type agreement, and optional header identities are checked.

Events are persisted in a durable inbox before returning 204. The event primary key deduplicates delivery; a body hash detects conflicting reuse. Processing is queued. Event and generation row locks plus a unique charge constraint cover concurrent delivery, different success event IDs, and polling races. Terminal states never regress. Unknown historical records are acknowledged without changing user data. Invalid snapshots are quarantined; permanent errors are not retried forever. The scheduler recovers unprocessed inbox rows for 24 hours. Inspect `webhook_events.failed_at/error_code` and logs for quarantined/exhausted events; correct the cause and replay the event deliberately. Keep host clocks synchronized.

## Testing and real-provider validation

```sh
php artisan test
vendor/bin/pint --test
npm run build
npx playwright test
```

`phpunit.xml` uses `forma_test`, with credentials inherited from `.env`. Tests exercise real MySQL transactions and triggers. `ConcurrencyTest` launches four independent PHP processes against the same generation/event rows. HTTP contract tests use documented fixtures and block stray network requests. They are **not** represented as real-provider validation.

Playwright requires Chrome (or change its configured channel to an installed Chromium). It uses the running app on 8030, creates a local test account, and calls the real public pricing endpoint. It does not submit billable generations. Screenshots and traces stay in ignored `test-results`.

Once the key is configured, register a user and run:

```sh
php artisan pixelforge:smoke your-studio-email@example.com
# Optional documented submission failure cases:
php artisan pixelforge:smoke your-studio-email@example.com --include-errors
```

The smoke command uses the same submission service as the web UI and queues real low-quality `[succeed] [duplicate]`, `[fail]`, and `[slow] [succeed]` scenarios. Optional `[blocked]` and `[overloaded]` prompts exercise create rejections. Every command invocation creates new, clearly labeled test generations. Keep the worker and scheduler running. Check that images load, failures cost zero, and duplicate callbacks leave one ledger entry. `[slow]` intentionally takes 60–90 seconds. `[overloaded]` intentionally remains unavailable until automatic retries stop.

After completion, reconcile by **UTC completion day**, using the same key:

```sh
php artisan pixelforge:reconcile 2026-11-23 2026-11-23
```

Date ranges are inclusive, at most 92 days. The command compares decimal strings exactly and exits nonzero for a mismatch. External generations created with the same key, unresolved requests, or the wrong completion date explain discrepancies; never alter the ledger to force a match. Use a dedicated key for a clean assessment report.

## Deployment

See [deployment runbook](docs/DEPLOYMENT.md). Public deployment needs a configured host, persistent MySQL, HTTPS, background worker, scheduler, and a dedicated production PixelForge key. The document contains build commands and verification steps; it does not claim a deployment exists.

## Deliberate limitations

- Signed images expire 24 hours after completion. Forma shows an expired-link state; original images are not archived. This is allowed by the assessment. Refreshing the provider URL does not extend its expiry.
- No wallet, purchase simulation, real payments, admin web dashboard, email verification, password-reset mail flow, or image uploads. Registration/login/logout and authenticated profile/password changes are implemented.
- No automatic retry of a terminal provider failure; a new user action starts a new billable identity.
- Provider keys are environment configuration, not per-user credentials. Old-key requests require the original key for polling recovery.
- One in-flight provider HTTP call per account favors understandable correctness over maximum throughput. Limits are shared across nodes only if they share the database cache and scope.
- Ledger triggers and the verified concurrency suite target MySQL/MariaDB.
- Public deployment, GitHub publication, and live authenticated smoke verification remain external setup tasks until access is available.
