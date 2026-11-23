<?php

namespace Tests\Feature;

use App\Jobs\AdvanceGeneration;
use App\Jobs\ProcessWebhook;
use App\Services\FinalizeGeneration;
use App\Services\PixelForge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\ProviderFixtures;
use Tests\TestCase;

class WorkerTest extends TestCase
{
    use ProviderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pixelforge.api_key' => 'test-key']);
        Http::preventStrayRequests();
    }

    private function advance($g): void
    {
        (new AdvanceGeneration($g->id))->handle(app(PixelForge::class), app(FinalizeGeneration::class));
    }

    public function test_acceptance_then_polling_completes_when_callback_is_missing(): void
    {
        $g = $this->generation();
        $this->fakeHttp(['*/generations' => Http::response($this->snapshot($g, 'queued'), 202)]);
        $this->advance($g);
        $g->refresh();
        $this->assertNotNull($g->provider_id);
        Http::assertSent(fn ($r) => $r->hasHeader('Idempotency-Key', $g->id) && $r->hasHeader('Authorization', 'Bearer test-key') && $r['metadata']['local_id'] === $g->id);
        $this->fakeHttp(['*/generations/*' => Http::response($this->snapshot($g))]);
        $this->travel(16)->seconds();
        $this->advance($g);
        $this->assertSame('succeeded', $g->fresh()->status);
        $this->assertDatabaseCount('generation_charges', 1);
    }

    public function test_timeout_is_retried_with_identical_payload_and_key(): void
    {
        $g = $this->generation();
        $this->fakeHttp(['*' => Http::failedConnection()]);
        $this->advance($g);
        $g->refresh();
        $this->assertSame('submitting', $g->status);
        $this->assertNull($g->actual_customer_price);
        $original = $g->request_payload;
        $g->update(['next_attempt_at' => now()]);
        $this->fakeHttp(['*/generations' => Http::response($this->snapshot($g), 202)]);
        $this->advance($g);
        Http::assertSent(fn ($r) => $r->hasHeader('Idempotency-Key', $g->id) && $r->data() === $original);
        $this->assertDatabaseCount('generation_charges', 1);
        $this->assertSame(2, $g->fresh()->submission_attempts);
    }

    public function test_overload_and_rate_limits_respect_retry_after(): void
    {
        foreach (['overloaded' => 503, 'rate_limit_exceeded' => 429, 'concurrency_limit_exceeded' => 429] as $code => $status) {
            Cache::flush();
            $g = $this->generation();
            $this->fakeHttp(['*' => Http::response(['error' => ['code' => $code]], $status, ['Retry-After' => '30'])]);
            $this->advance($g);
            $g->refresh();
            $this->assertSame($code, $g->error_code);
            $this->assertGreaterThanOrEqual(29, now()->diffInSeconds($g->next_attempt_at));
            $this->assertNull($g->actual_customer_price);
            $g->update(['slot_reserved' => false, 'status' => 'attention']);
        }
    }

    public function test_known_rejections_are_terminal_and_free(): void
    {
        foreach (['content_policy_violation' => 400, 'daily_limit_exceeded' => 429, 'validation_failed' => 422] as $code => $status) {
            $g = $this->generation();
            $this->fakeHttp(['*' => Http::response(['error' => ['code' => $code]], $status)]);
            $this->advance($g);
            $g->refresh();
            $this->assertSame('failed', $g->status);
            $this->assertFalse($g->slot_reserved);
            $this->assertSame('0.000000000000000000', $g->actual_customer_price);
        }
        $this->assertDatabaseCount('generation_charges', 0);
    }

    public function test_ambiguous_exhaustion_requires_review_instead_of_claiming_failure(): void
    {
        $g = $this->generation(['consecutive_errors' => 7]);
        $this->fakeHttp(['*' => Http::failedConnection()]);
        $this->advance($g);
        $g->refresh();
        $this->assertSame('attention', $g->status);
        $this->assertTrue($g->slot_reserved);
        $this->assertNull($g->actual_customer_price);
        $this->assertNull($g->next_attempt_at);
    }

    public function test_no_fourth_generation_is_submitted_until_capacity_is_available(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->generation(['provider_id' => 'gen_busy_'.$i, 'status' => 'processing', 'slot_reserved' => true]);
        }
        $g = $this->generation();
        $this->fakeHttp();
        $this->advance($g);
        Http::assertNothingSent();
        $this->assertSame('queued', $g->fresh()->status);
    }

    public function test_local_request_rate_budget_defers_without_network_request(): void
    {
        $scope = app(PixelForge::class)->scope().':requests';
        for ($i = 0; $i < 50; $i++) {
            RateLimiter::hit($scope, 60);
        }
        $g = $this->generation();
        $this->fakeHttp();
        $this->advance($g);
        Http::assertNothingSent();
        $this->assertSame('local_rate_limit', $g->fresh()->error_code);
    }

    public function test_stuck_generation_and_inbox_are_recovered_durably(): void
    {
        Queue::fake();
        $g = $this->generation();
        DB::table('webhook_events')->insert(['id' => 'evt_pending', 'type' => 'ping', 'payload' => '{}', 'body_hash' => str_repeat('a', 64), 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('pixelforge:recover')->assertSuccessful();
        Queue::assertPushed(AdvanceGeneration::class, fn ($j) => $j->generationId === $g->id);
        Queue::assertPushed(ProcessWebhook::class, fn ($j) => $j->eventId === 'evt_pending');
    }

    public function test_slow_generation_remains_processing_and_is_not_charged_early(): void
    {
        $g = $this->generation(['provider_id' => 'gen_slow', 'status' => 'processing', 'slot_reserved' => true]);
        $this->fakeHttp(['*' => Http::response($this->snapshot($g, 'processing'))]);
        $this->advance($g);
        $this->travel(90)->seconds();
        $this->advance($g);
        $this->assertSame('processing', $g->fresh()->status);
        $this->assertDatabaseCount('generation_charges', 0);
    }

    public function test_key_change_is_not_silently_used_for_an_old_request(): void
    {
        $g = $this->generation();
        config(['pixelforge.api_key' => 'different-key']);
        $this->fakeHttp();
        $this->advance($g);
        Http::assertNothingSent();
        $this->assertSame('attention', $g->fresh()->status);
    }

    public function test_operator_recovery_preserves_identity_and_extends_expired_window(): void
    {
        Queue::fake();
        $g = $this->generation(['created_at' => now()->subDays(2), 'status' => 'attention']);
        $payload = $g->request_payload;
        $this->artisan('pixelforge:retry', ['generation' => $g->id])->assertSuccessful();
        $g->refresh();
        $this->assertSame($payload, $g->request_payload);
        $this->assertTrue($g->recovery_until->isFuture());
        $this->fakeHttp(['*/generations' => Http::response($this->snapshot($g), 202)]);
        $this->advance($g);
        $this->assertSame('succeeded', $g->fresh()->status);
        $this->artisan('pixelforge:retry', ['generation' => $g->id])->assertFailed();
        $this->assertSame('succeeded', $g->fresh()->status);
        $this->assertDatabaseCount('generation_charges', 1);
    }

    public function test_changed_provider_identity_is_not_accepted(): void
    {
        $g = $this->generation(['provider_id' => 'gen_original', 'status' => 'processing']);
        $object = $this->snapshot($g);
        $object['id'] = 'gen_wrong';
        $this->fakeHttp(['*' => Http::response($object)]);
        $this->advance($g);
        $this->assertDatabaseCount('generation_charges', 0);
        $this->assertSame('gen_original', $g->fresh()->provider_id);
    }

    public function test_reconciliation_compares_exact_completed_day_costs(): void
    {
        $g = $this->generation();
        app(FinalizeGeneration::class)->apply($g->id, $this->snapshot($g));
        $this->fakeHttp(['*/usage*' => Http::response(['data' => [['date' => now()->toDateString(), 'cost_usd' => '0.305510000']]])]);
        $this->artisan('pixelforge:reconcile', ['start' => now()->toDateString(), 'end' => now()->toDateString()])->assertSuccessful();
        $this->fakeHttp(['*/usage*' => Http::response(['data' => [['date' => now()->toDateString(), 'cost_usd' => '0.305510001']]])]);
        $this->artisan('pixelforge:reconcile', ['start' => now()->toDateString(), 'end' => now()->toDateString()])->assertFailed();
    }
}
