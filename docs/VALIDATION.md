# Validation record

Local validation date: 2026-11-23. Workspace: new `C:\xampp\htdocs\promptedit-pixelforge` only.

## Executed

- Read the complete real PixelForge API documentation and assessment.
- Retrieved real public `/v1/pricing`; confirmed the documented pricing structure.
- Installed Laravel 13.43.0 on PHP 8.3.30 and built the Vue/Inertia/TypeScript frontend.
- Provisioned an isolated MariaDB 11.4.14 instance on loopback port 3308. Existing XAMPP data was not modified.
- Ran `php artisan test` after the submission-service refactor, operator-recovery locking fix, and PHP formatting: **38 tests, 200 assertions passed**.
- The backend suite includes four genuinely concurrent PHP webhook processes, real InnoDB transactions, uniqueness constraints and immutable-ledger triggers.
- Ran the production frontend build with TypeScript checking successfully.
- Started the local app on `http://127.0.0.1:8030`, plus a queue listener and scheduler. `/login` returned HTTP 200.
- Chrome smoke testing passed registration, authenticated dashboard, live public pricing, mobile navigation/layout with no horizontal overflow, profile updates, logout, and login.
- Inspected desktop and mobile creation screenshots. Fixed secondary text contrast issues; the final Chrome smoke run passed with **zero axe WCAG 2 A/AA and 2.1 AA violations on the creation page**. This is automated coverage of that page, not a claim of full accessibility certification.
- `composer audit` reported no security vulnerability advisories; `npm audit` reported zero vulnerabilities.
- Production route and Blade-view cache compilation succeeded; caches were cleared afterward so local configuration remains editable.
- `vendor/bin/pint --test` passed. Staged files were checked against the actual local secret values without printing them; `.env`, runtime databases, logs, screenshots and dependencies are excluded from Git.

## Real provider verification added

- After the user configured the key and signing secret, real authentication, generation, failure, slow completion, reference billing, browser image display, and usage reconciliation passed. Full evidence: [LIVE-VALIDATION.md](LIVE-VALIDATION.md).
- A public webhook-only tunnel returned HTTP 200 at its readiness endpoint. The key's dashboard URL has subsequently been configured: real `ping`, `generation.processing`, and `generation.succeeded` events were received and processed without errors.
- A later real user generation completed successfully. The latest provider total, **0.101797500 USD**, reconciled exactly; four successes have four ledger entries totaling **0.127246875 USD** in customer charges.
- PHPUnit scenarios still use isolated contract fixtures. The separate opt-in browser test was executed against the actual provider.

## Submission status

- No public deployment URL exists from this work yet.
- The supplied public source repository is https://github.com/Nicanderrr/PixelForge.

## Operational notes

The local `.env` has correct app, database, session, queue, and user-supplied development provider credentials. Local browser tests created `browser-…@example.test` accounts; live verification uses a separate reviewer account whose current credentials are saved only in ignored `.runtime/reviewer.json`. Its images and cost records come from real PixelForge API calls. No fabricated provider records were inserted into the application database.

Negative automated scenarios intentionally emit test-environment log entries (pricing outage, quarantined snapshot, mismatched identity). These are expected test evidence, not successful live provider requests. Inspect the log environment and timestamp when diagnosing errors.

Bonus admin reporting and wallet features remain unimplemented while public deployment validation is outstanding. These features are explicitly optional in the original assessment. The operator-only reconciliation CLI is implemented and passed both fixture and real provider checks.
