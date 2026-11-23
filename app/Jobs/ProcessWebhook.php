<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\FinalizeGeneration;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ProcessWebhook implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $uniqueFor = 120;

    public function __construct(public string $eventId) {}

    public function uniqueId(): string
    {
        return $this->eventId;
    }

    public function backoff(): array
    {
        return [10, 30, 120, 300];
    }

    public function handle(FinalizeGeneration $finalizer): void
    {
        DB::transaction(function () use ($finalizer) {
            $event = DB::table('webhook_events')->where('id', $this->eventId)->lockForUpdate()->first();
            if (! $event || $event->processed_at || $event->failed_at) {
                return;
            }
            if ($event->type !== 'ping') {
                $payload = json_decode($event->payload, true, flags: JSON_THROW_ON_ERROR);
                $object = $payload['data']['object'];
                $localId = $object['metadata']['local_id'] ?? null;
                // Keys can have historical generations from another deployment. Ignore unknown records.
                if ($localId && Generation::whereKey($localId)->exists()) {
                    try {
                        $finalizer->apply($localId, $object);
                    } catch (ValidationException|\InvalidArgumentException|\UnexpectedValueException $e) {
                        DB::table('webhook_events')->where('id', $this->eventId)->update(['failed_at' => now(), 'error_code' => 'invalid_snapshot']);
                        Log::warning('Webhook quarantined', ['event_id' => $this->eventId]);

                        return;
                    }
                }
            }
            DB::table('webhook_events')->where('id', $this->eventId)->update(['processed_at' => now(), 'updated_at' => now()]);
        }, 5);
    }

    public function failed(?\Throwable $exception): void
    {
        DB::table('webhook_events')->where('id', $this->eventId)->whereNull('processed_at')
            ->update(['failed_at' => now(), 'error_code' => 'processing_exhausted']);
    }
}
