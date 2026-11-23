# Documentation-led implementation

Read on 2026-11-23: https://pixelforge.promptedit.dev/docs and https://pixelforge.promptedit.dev/assessment. Public pricing was fetched successfully from the actual provider.

## Contract inventory

| Area | Documented behavior used by the implementation |
| --- | --- |
| Authentication | Server-side bearer API key; each key has separate usage and webhook configuration |
| Create | `POST /v1/generations`, JSON, returns 202 and a generation object |
| Input | Required prompt, maximum 4,000 characters; optional defaults are made explicit in our payload |
| Sizes | `1024x1024`, `1536x1024`, `1024x1536` |
| Quality | `low`, `medium`, `high` |
| References | Up to four HTTP(S) URLs in `reference_images`; billed without provider downloading them |
| Correlation | String metadata; our single key is `local_id` |
| Poll | `GET /v1/generations/{id}` |
| List | `GET /v1/generations`, cursor pagination, status filter, limit 1–100; available for manual investigation |
| Identity | `Idempotency-Key`, account-scoped, permanent; replay uses identical parameters and returns the same generation |
| Rates | `GET /v1/pricing`, no authentication; use each completed generation’s own `rates` for final accounting |
| Actual usage | `input_text_tokens`, `input_image_tokens`, `output_tokens`, `reasoning_tokens`, `total_tokens`; available on success only |
| Limits | Account-wide 60 authenticated requests/minute, three concurrent generations, 500/day UTC |
| Retry | Observe `Retry-After`; transient rate/concurrency and overload errors back off; network failures require idempotency |
| Failure | Create policy/validation/quota rejections create nothing; terminal generation failures (`generation_failed`, `internal_error`) are free |
| Usage report | `GET /v1/usage`, per-key, inclusive dates, up to 92 days; billed on UTC completion day; exact 9-decimal USD |
| Events | Processing, succeeded, failed, and dashboard ping; full snapshot at `data.object`; deduplicate event `id` |
| Signature | HMAC-SHA256 of timestamp, dot, raw body; hex signature; constant-time comparison and ±300s freshness |
| Delivery | Any order, at least once; can precede POST response; six total attempts; 2xx within 10 seconds acknowledges |
| Retries | Provider delivery delays: 10s, 30s, 2m, 10m, 15m; callbacks are not guaranteed |
| Images | Signed PNG; expires 24 hours after completion; retrieving again does not extend expiry |
| Test keywords | `[blocked]`, `[overloaded]`, `[fail]`, `[succeed]`, `[slow]`, `[duplicate]` |

## Execution plan and choices

1. Create a new isolated project, runtime, and database. Preserve all existing workspace projects.
2. Build auth and ownership boundaries before exposing generation records.
3. Calculate estimates from live pricing; refuse unpriced submissions when pricing cannot be fetched.
4. Persist the original payload and UUID before queue dispatch. Schedule durable recovery independently of job delivery.
5. Use one finalization service for polling, early callbacks, replays, and concurrent delivery. Keep network calls outside transactions.
6. Make accounting exact and append-only, then test duplicate and overlapping terminal updates on InnoDB.
7. Build the studio UI and validate desktop/mobile behavior and accessible controls.
8. Test authenticated real scenarios after a dedicated key is supplied. Reconcile exact costs before claiming the provider integration verified.
9. Prepare deployment and a public repository; do not fabricate public URLs.

This inventory is a description of the contract, not a frozen production pricing table. Fixture rates in tests come from documented examples. Runtime rates always come from PixelForge.
