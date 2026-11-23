# Technical interview preparation

## A short walkthrough

Start at `routes/web.php`, then `GenerationRequest`, `RequestGeneration`, `AdvanceGeneration`, and `FinalizeGeneration`. Show `WebhookController` and `ProcessWebhook` next. Finish with `Money`, the migrations, and the concurrency test. The UI is in `resources/js/Pages` and all prices cross the Inertia boundary as strings.

## Why decimals rather than cents?

One token can cost a fraction of a cent; multiplying by 1.25 adds more fractional digits. Rounding each generation to cents would systematically distort charges and reconciliation. BCMath strings and `DECIMAL(30,18)` preserve the supported rates exactly. The arithmetic precision contract accepts rate strings with up to nine decimals, giving room for the six-decimal token divisor and two-decimal markup. Invalid precision causes an exception rather than hidden truncation.

Show the documented maximum example in `MoneyTest`: provider $0.373875, customer $0.46734375. UI rounding to six decimal places does not change either ledger value. Six-place formatting in TypeScript uses BigInt, not `parseFloat` or `toFixed`.

## Why a separate charge table?

Generation rows contain operational state and estimates. Charges are finalized accounting facts. A unique `generation_id` gives the database a hard rule: only one charge per generation. Finalization locks the generation and inserts the ledger entry in one transaction. Both either commit or roll back. MySQL triggers block edits/deletes. A future refund should be a separate signed adjustment entry; overwriting a charge would destroy its history.

## Three forms of idempotency

1. **Browser:** user + request UUID prevents a double click from creating two local requests. Reuse with changed inputs is rejected.
2. **Provider:** local generation UUID is the permanent provider header key; the exact original payload is stored. A timeout can safely replay that payload.
3. **Completion:** event primary key deduplicates deliveries, generation row locks serialize state changes, and the unique charge key prevents duplicate accounting even across different success event IDs or polling.

These protections solve different problems. A webhook event key alone cannot prevent polling from charging again, and a provider key alone cannot prevent a double click that creates two local UUIDs.

## What if the provider accepted a request but the network timed out?

We do not mark it failed or send a new identity. The reserved slot and due record remain. Retry uses the same UUID and immutable payload. If automatic retries are exhausted, status becomes `attention`, with no invented final cost. An operator can recover the same request. The provider’s permanent idempotency guarantee is what makes POST replay safe.

## Why webhooks and polling?

Webhooks deliver low-latency updates, but the documentation explicitly does not guarantee delivery. A scheduler checks durable due records. Polling and webhooks share the finalizer so there is one definition of success, failure, and billing. Scheduled recovery also covers a crash between committing the generation and dispatching its job.

## Early and out-of-order events

The provider can deliver success before POST returns. Metadata identifies the local record before its provider ID is saved. Finalization verifies provider identity and locks the record. A delayed queued/processing acknowledgment cannot regress a completed record. Conflicting terminal snapshots are ignored after the first committed terminal state because the documented lifecycle makes terminal states immutable. Such provider behavior would warrant operational investigation, not silently rewriting money.

## Signature verification

The hash input is the timestamp text, a literal period, and the original raw bytes. Parsing and serializing JSON first could change whitespace/order and break verification. Verify with the key’s signing secret, compare with `hash_equals`, and check timestamp freshness before acting on JSON. Event IDs make fresh-timestamp retries harmless. Signature verification is separate from payload validation.

## Concurrency and queues

There are two different limits. The shared cache lock serializes admission and short provider calls for the whole account. Durable reserved slots limit local pending/active work to three. The database transaction only protects state and accounting; it never holds a row lock across a 20-second network timeout. This sacrifices some peak throughput for a small, explainable design. Multiple app nodes must use a shared cache backend and the same account scope.

## Why retain an ambiguous reservation?

Releasing it might allow a fourth provider generation while the first is actually running. Conservative reservations can block throughput after errors. The UI exposes attention status and the CLI allows deliberate investigation/recovery. A more advanced version could reconcile provider listings automatically, but it must not guess which generation belongs to a request.

## What proves concurrency safety?

The suite uses MySQL/MariaDB, not SQLite. `ConcurrencyTest` starts four independent PHP processes: two duplicate deliveries of one event, another success event, and an old processing event. They contend for actual InnoDB locks. Assertions verify all events processed, a terminal success, and exactly one exact charge. Other tests cover invalid signatures, corrupted usage, mismatched IDs, free failures, changed rates, missed callbacks, overloads, timeouts, rate budgets, and user isolation.

## Reconciliation

Sum only finalized provider costs, by UTC completion day and original API-key fingerprint. Compare to `/v1/usage` using decimal comparison. Estimates and customer markup never enter that sum. A mismatch can mean an unresolved request, external use of the same API key, or a wrong date/key. The command reports differences and exits nonzero; it never adjusts ledger amounts to hide a discrepancy.

## Likely extensions

- **Wallet:** reserve a conservative maximum atomically against the user balance, record the reservation in a separate ledger, release it on failure, and settle only actual success. If prices change or final usage exceeds a reservation, define a policy before allowing a negative balance. There is no wallet in this version.
- **Admin reports:** authorize an explicit role, aggregate only finalized immutable entries, group by completion day/user, and expose reconciliation differences without conflating estimates.
- **Image storage:** queue a controlled download before 24-hour expiry, allowlist provider URLs, bound download size/time, and store privately with authorization. Do not fetch arbitrary user reference URLs.
- **Higher throughput:** separate rate-budget acquisition from admission and polling, keep atomic shared capacity reservations, and preserve all idempotency/locking guarantees.
- **Credential rotation:** retain a secure key-reference registry rather than only environment credentials, so old generations can still be reconciled without restoring a global key.

## Be candid about verification

Automated HTTP fixtures prove application behavior for the documented contract. They do not prove a live API key, reachable webhook URL, deployment configuration, or actual provider totals. Read `VALIDATION.md` and distinguish executed checks from the remaining external checks in the interview.
