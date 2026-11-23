# Production deployment runbook

No public deployment has been made yet. This is the concrete configuration/runbook for Laravel Cloud or a Linux PHP host with persistent workers. Use a dedicated production PixelForge key and a new production database.

## Runtime resources

- PHP 8.3+ with `bcmath`, `pdo_mysql`, `curl`, `mbstring`, `openssl`, `fileinfo`, `xml` and `zip`
- MySQL 8.4 or supported MariaDB; migrate with a deployment user that can create triggers
- HTTPS web origin with document root **`public/`**, never the repository root
- One or more supervised Laravel queue workers
- Laravel scheduler via cron or the hosting platform scheduler
- Persistent storage for logs; all app nodes share database cache, sessions, queue, and account scope

Do not deploy the local `.runtime` directory, test database, `.env`, screenshots, or development database binaries. `.gitignore` excludes them.

## Environment

Use the hosting platform’s secret store. Do not paste keys into Git or build logs.

```dotenv
APP_NAME=Forma
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR-ACTUAL-DOMAIN
APP_KEY=GENERATE_ONCE_AND_STORE_SECURELY
DB_CONNECTION=mysql
DB_HOST=YOUR-DATABASE-HOST
DB_PORT=3306
DB_DATABASE=forma_production
DB_USERNAME=YOUR-RUNTIME-USER
DB_PASSWORD=YOUR-SECRET
QUEUE_CONNECTION=database
CACHE_STORE=database
CACHE_PREFIX=forma_production_
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
PIXELFORGE_API_KEY=DEDICATED_PRODUCTION_KEY
PIXELFORGE_WEBHOOK_SECRET=THAT_KEYS_SIGNING_SECRET
PIXELFORGE_BASE_URL=https://pixelforge.promptedit.dev/v1
PIXELFORGE_ACCOUNT_SCOPE=YOUR_PROVIDER_ACCOUNT
LOG_CHANNEL=stack
LOG_STACK=single
LOG_LEVEL=warning
```

Set actual values, not these placeholders. All nodes for this environment must share `APP_KEY`. Changing it invalidates encrypted sessions. Keep a secure backup. Only trust reverse proxy headers from your actual ingress; configure Laravel trusted proxies for the host’s documented proxy addresses if necessary. Verify HTTPS redirects/cookies at the public URL.

## Build and release

Build with the pinned lockfiles:

```sh
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm ci
npm run build
```

Once runtime environment values are present:

```sh
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan schedule:interrupt
```

Run migrations once per release, before new workers process requests. Never run `migrate:fresh` on production. A runtime DB user can have narrower privileges than the migration user; do not give it administrative access to unrelated databases.

## Workers and scheduler

Supervise this long-running process (Laravel Cloud background process, systemd, or Supervisor):

```sh
php artisan queue:work database --sleep=1 --tries=3 --timeout=45 --max-time=3600
```

Queue `retry_after` is 90 seconds, larger than the worker timeout and HTTP timeout. Supervision should restart workers after exit. Job-specific retry counts apply to infrastructure failures; provider retry decisions are persisted on generation records independently.

Cron once each minute:

```cron
* * * * * cd /path/to/forma && php artisan schedule:run >> /dev/null 2>&1
```

Laravel runs the sub-minute recovery task every ten seconds during that invocation. Do not add another loop dispatching the same work from the frontend. Use one scheduler or a coordinated shared cache. Alert on failed jobs, `attention` generations, and quarantined webhook events.

## PixelForge dashboard

1. Create a dedicated production key; store it securely in the host environment.
2. Set that key’s webhook URL to `https://YOUR-ACTUAL-DOMAIN/webhooks/pixelforge`.
3. Copy its signing secret into `PIXELFORGE_WEBHOOK_SECRET`.
4. Rebuild config cache and restart workers after changing secrets.
5. Send the dashboard test event; require a 204 response and a processed inbox record.
6. Confirm there is no redirect on the webhook URL. Keep the host clock synchronized.

The provider retries non-2xx responses/timeouts. No special public CORS policy is needed: webhooks are server-to-server requests.

## Release acceptance checks

- Public `/up` and `/login` return 200 over HTTPS.
- An unauthenticated `/dashboard` request redirects to login.
- Register and sign in; all assets load without a Vite development server.
- The environment never returns stack traces or secrets in HTTP responses.
- Generate a real `[succeed] [duplicate]` image and open it before expiry.
- Generate `[fail]` and verify zero final charge and no ledger entry.
- Generate `[slow] [succeed]`; navigate away and back to verify durable progress.
- Resend a success webhook from the provider dashboard; verify one charge remains.
- Temporarily disable callback delivery for a test request; verify polling recovery.
- Run `pixelforge:reconcile START END` for UTC completion dates and require zero differences.
- Check queue failures and application logs.
- Leave a few real completed examples in the assessment account, then record the verified public URL in `docs/VALIDATION.md`.

## GitHub publication

The local repository is prepared separately from all other workspace projects. Before publishing:

```sh
git status --short
git ls-files .env
git ls-files .runtime
git log --oneline
composer audit
npm audit
```

The two `git ls-files` commands must produce no output. Review staged files for credentials. Then create an empty public GitHub repository in the intended account, add its URL as `origin`, and push `main` using authorized GitHub credentials. Do not embed a personal token in the remote URL. No remote or public URL should be reported until actually created and verified.

GitHub Actions runs the backend suite against a disposable MySQL service and checks PHP formatting and the production build. Its service password is an intentionally disposable CI test value, not a real credential. Browser/live provider checks are manual because they require a running application and optional provider/tunnel setup.
