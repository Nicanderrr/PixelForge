<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Services\FinalizeGeneration;
use App\Services\PixelForge;
use App\Services\ProviderException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdvanceGeneration implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public int $tries = 3;

    public int $timeout = 45;

    public function __construct(public string $generationId) {}

    public function uniqueId(): string
    {
        return $this->generationId;
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(PixelForge $provider, FinalizeGeneration $finalizer): void
    {
        // Serializes account admission and authenticated calls across all workers.
        // TTL exceeds the hard HTTP timeout. No network I/O inside a DB transaction.
        $lock = Cache::lock($provider->scope().':dispatch', 60);
        if (! $lock->get()) {
            return;
        } // Durable due row is picked up by the scheduler.
        try {
            $g = Generation::find($this->generationId);
            if (! $g || $g->isTerminal() || $g->status === 'attention' || ($g->next_attempt_at && $g->next_attempt_at->isFuture())) {
                return;
            }
            if ($g->provider_key_fingerprint !== $provider->fingerprint()) {
                $this->attention($g, 'key_changed', 'The provider key changed. Restore the original key before recovering this request.');

                return;
            }
            if (($g->recovery_until ?? $g->created_at->copy()->addDay())->isPast()) {
                $this->attention($g, 'recovery_required', 'This request needs a status check by the operator. No final charge has been recorded.');

                return;
            }
            if (! $g->provider_id && ! $g->slot_reserved) {
                if (Generation::where('slot_reserved', true)->count() >= config('pixelforge.concurrency')) {
                    $g->update(['next_attempt_at' => now()->addSeconds(15)]);

                    return;
                }
                $g->update(['slot_reserved' => true, 'status' => 'submitting']);
            }
            $isSubmission = ! $g->provider_id;
            if ($isSubmission) {
                $g->increment('submission_attempts');
            }
            try {
                $object = $isSubmission
                    ? $provider->request('POST', '/generations', $g->request_payload, $g->id)
                    : $provider->request('GET', '/generations/'.rawurlencode($g->provider_id));
                $finalizer->apply($g->id, $object);
            } catch (ProviderException $e) {
                $this->handleError($g, $e, $isSubmission);
            } catch (ValidationException|\InvalidArgumentException|\UnexpectedValueException $e) {
                Log::warning('Invalid provider snapshot', ['generation_id' => $g->id, 'exception' => $e::class]);
                $this->handleError($g, new ProviderException('invalid_response', true, 0, $isSubmission), $isSubmission);
            }
        } finally {
            $lock->release();
        }
    }

    private function handleError(Generation $generation, ProviderException $e, bool $submission): void
    {
        DB::transaction(function () use ($generation, $e, $submission) {
            $g = Generation::lockForUpdate()->findOrFail($generation->id);
            if ($g->isTerminal()) {
                return;
            } // Webhook may have finalized while HTTP was in flight.
            $attempt = $g->consecutive_errors + 1;
            $g->consecutive_errors = $attempt;
            $g->error_code = $e->errorCode;
            // Only a documented, definitive create rejection can establish a free terminal failure.
            if ($submission && ! $g->provider_id && ! $e->ambiguous && in_array($e->errorCode, ['content_policy_violation', 'validation_failed', 'daily_limit_exceeded'], true)) {
                $g->fill(['status' => 'failed', 'slot_reserved' => false, 'actual_provider_cost' => '0',
                    'actual_customer_price' => '0', 'completed_at' => now(), 'next_attempt_at' => null,
                    'error_message' => match ($e->errorCode) {
                        'content_policy_violation' => 'PixelForge rejected this prompt under its content policy. You were not charged.',
                        'daily_limit_exceeded' => 'The provider daily quota has been reached. Try again tomorrow. You were not charged.',
                        default => 'PixelForge rejected the request parameters. You were not charged.',
                    }]);
            } elseif (! $e->retryable || $attempt >= config('pixelforge.max_attempts')) {
                $g->fill(['status' => 'attention', 'next_attempt_at' => null,
                    'error_message' => 'Automatic retries have stopped. The operator can safely recover this request with the same idempotency key. No final charge has been recorded.']);
            } else {
                $delay = max($e->retryAfter, min(300, 2 ** $attempt) + random_int(0, 5));
                $g->fill(['next_attempt_at' => now()->addSeconds($delay),
                    'error_message' => 'The provider is temporarily unavailable or busy. We will retry this request safely.']);
            }
            $g->save();
        }, 5);
    }

    private function attention(Generation $g, string $code, string $message): void
    {
        Generation::whereKey($g->id)->whereNotIn('status', ['succeeded', 'failed'])->update([
            'status' => 'attention', 'error_code' => $code, 'error_message' => $message, 'next_attempt_at' => null,
        ]);
    }
}
