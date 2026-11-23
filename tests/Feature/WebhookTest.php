<?php

namespace Tests\Feature;

use App\Services\FinalizeGeneration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProviderFixtures;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use ProviderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['pixelforge.webhook_secret' => 'whsec_test']);
    }

    public function test_valid_signature_finalizes_once_even_for_duplicate_events_and_different_event_ids(): void
    {
        $g = $this->generation();
        $event = $this->event($g);
        $this->signedPost($event)->assertNoContent();
        $this->signedPost($event)->assertNoContent();
        $this->signedPost($this->event($g))->assertNoContent();
        $this->assertDatabaseCount('generation_charges', 1);
        $this->assertDatabaseCount('webhook_events', 2);
        $g->refresh();
        $this->assertSame('succeeded', $g->status);
        $this->assertSame('0.305510000000000000', $g->actual_provider_cost);
        $this->assertSame('0.381887500000000000', $g->actual_customer_price);
    }

    public function test_out_of_order_processing_and_conflicting_terminal_events_cannot_regress_success(): void
    {
        $g = $this->generation();
        foreach (['succeeded', 'processing', 'failed'] as $status) {
            $this->signedPost($this->event($g, $status))->assertNoContent();
        }
        $this->assertSame('succeeded', $g->fresh()->status);
        $this->assertDatabaseCount('generation_charges', 1);
    }

    public function test_failed_generations_never_charge_or_regress(): void
    {
        $g = $this->generation();
        foreach (['failed', 'processing', 'succeeded'] as $status) {
            $this->signedPost($this->event($g, $status))->assertNoContent();
        }
        $g->refresh();
        $this->assertSame('failed', $g->status);
        $this->assertSame('0.000000000000000000', $g->actual_customer_price);
        $this->assertDatabaseCount('generation_charges', 0);
    }

    public function test_bad_signature_old_and_future_timestamps_are_rejected(): void
    {
        $g = $this->generation();
        $event = $this->event($g);
        $this->signedPost($event, secret: 'incorrect')->assertStatus(400);
        $this->signedPost($event, time() - 301)->assertStatus(400);
        $this->signedPost($event, time() + 301)->assertStatus(400);
        $this->postJson('/webhooks/pixelforge', $event)->assertStatus(400);
        $this->assertDatabaseCount('webhook_events', 0);
        $this->assertDatabaseCount('generation_charges', 0);
    }

    public function test_missing_secret_rejects_even_an_empty_secret_signature(): void
    {
        config(['pixelforge.webhook_secret' => '']);
        $this->signedPost($this->event($this->generation()), secret: '')->assertStatus(503);
    }

    public function test_early_webhook_wins_over_late_submission_acknowledgment(): void
    {
        $g = $this->generation(['status' => 'submitting']);
        $this->signedPost($this->event($g))->assertNoContent();
        app(FinalizeGeneration::class)->apply($g->id, $this->snapshot($g, 'queued'));
        $this->assertSame('succeeded', $g->fresh()->status);
        $this->assertDatabaseCount('generation_charges', 1);
    }

    public function test_snapshot_uses_locked_in_rates_and_quarantines_inconsistent_usage(): void
    {
        $g = $this->generation();
        $event = $this->event($g);
        $event['data']['object']['rates']['reasoning_tokens'] = '25.00';
        $this->signedPost($event)->assertNoContent();
        $this->assertSame('0.345310000000000000', $g->fresh()->actual_provider_cost);
        $g2 = $this->generation();
        $bad = $this->event($g2);
        $bad['data']['object']['usage']['total_tokens'] = 1;
        $this->signedPost($bad)->assertNoContent();
        $this->assertNotNull(DB::table('webhook_events')->where('id', $bad['id'])->value('failed_at'));
        $this->assertSame('queued', $g2->fresh()->status);
        $this->assertDatabaseCount('generation_charges', 1);
    }

    public function test_reusing_event_id_with_changed_body_is_rejected(): void
    {
        $g = $this->generation();
        $event = $this->event($g);
        $this->signedPost($event)->assertNoContent();
        $event['created_at'] = now()->subHour()->toIso8601String();
        $this->signedPost($event)->assertStatus(409);
    }

    public function test_ping_and_unknown_historical_generations_are_acknowledged(): void
    {
        $this->signedPost(['id' => 'evt_ping', 'type' => 'ping'])->assertNoContent();
        $event = $this->event($this->generation());
        $event['data']['object']['metadata']['local_id'] = 'unknown';
        $this->signedPost($event)->assertNoContent();
        $this->assertDatabaseCount('generation_charges', 0);
    }

    public function test_event_type_mismatch_is_rejected(): void
    {
        $event = $this->event($this->generation());
        $event['type'] = 'generation.processing';
        $this->signedPost($event)->assertStatus(400);
    }

    public function test_ledger_rows_are_immutable_in_mysql(): void
    {
        $g = $this->generation();
        app(FinalizeGeneration::class)->apply($g->id, $this->snapshot($g));
        $this->expectException(QueryException::class);
        DB::table('generation_charges')->where('generation_id', $g->id)->update(['customer_charge' => '99']);
    }
}
