# Real PixelForge verification — 2026-11-23 UTC

These checks used the actual assessment provider with the user's dedicated development API key. No provider responses or output images were faked. The key and signing secret remain in ignored `.env`.

## Baseline and completed results

`GET /v1/usage` authenticated successfully and initially reported zero generations/cost for this key on 2026-11-23.

| Scenario | Observed result | Exact provider USD | Exact customer USD |
| --- | --- | --- | --- |
| `[succeed] [duplicate]` ceramic vase | Succeeded; recovered after a transient submission retry | 0.012867500 | 0.016084375 |
| `[fail]` | Accepted, then `generation_failed`; no charge entry | 0 | 0 |
| `[slow] [succeed]` sunrise | Succeeded after delayed processing | 0.015602500 | 0.019503125 |
| `[blocked]` | Submission rejected with `content_policy_violation`; no charge entry | 0 | 0 |
| Browser `[succeed] [duplicate]` with one reference URL | Succeeded; 1024×1024 image loaded; 765 reference tokens displayed | 0.023510000 | 0.029387500 |

Three successful generations produced exactly three charge rows. Customer totals equal provider totals multiplied by 1.25, without rounding accounting entries.

The `[overloaded]` scenario produced documented overload responses and exponential retry scheduling. It stopped automatically after **eight submission attempts**, entered `attention`, retained no provider ID, and recorded no charge. No failed queue jobs were present. It remains an explicit operator-review record rather than pretending an image was generated.

## Reconciliation

Executed:

```sh
php artisan pixelforge:reconcile 2026-11-23 2026-11-23
```

Observed:

```text
Local provider cost: 0.051980000000000000 USD
PixelForge reported: 0.051980000 USD
Difference:         0.000000000000000000 USD
```

The command exited successfully. This is a real-provider reconciliation, separate from the fixture-based automated tests. Total finalized customer charges for these three successes are 0.064975000 USD.

## Browser verification

The opt-in Playwright live test signed into the reviewer account, submitted a real request through the app, waited for final status, loaded the returned image, verified its natural width of 1024, checked reference-image usage, and exposed the exact accounting amounts. It passed in approximately two minutes while the other smoke scenarios were running.

The local reviewer account is `live-verification@example.test`. Its random password is available only in ignored `.runtime/reviewer.json`; it is not a shared production credential. Screenshot evidence is saved at ignored `test-results/live-generation.png`.

## Callback boundary

At the last observation, the provider webhook inbox was empty. Completed live generations were finalized through documented polling. The `[duplicate]` keyword requests duplicate delivery from PixelForge, but it does **not** establish that callbacks were received.

A temporary Cloudflare tunnel to the webhook-only relay was created and its public `/health` endpoint returned HTTP 200. The relay exposes no application pages or development exception bodies. The user still needs to configure the key's dashboard webhook URL and send a test event, followed by another duplicate scenario or resending recorded provider events.

An attempt to inspect the signed-in provider dashboard using Windows computer use was stopped by the tool's safety check because it could not determine the current browser URL. No dashboard settings were changed through that session.

The temporary tunnel is development infrastructure, not a durable production deployment. Public hosting and GitHub publication remain outstanding.
