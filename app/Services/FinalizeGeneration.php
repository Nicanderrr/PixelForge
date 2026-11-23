<?php

namespace App\Services;

use App\Models\Generation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class FinalizeGeneration
{
    /** All HTTP responses and webhook snapshots use this same locked state transition. */
    public function apply(string $localId, array $object): void
    {
        Validator::make($object, [
            'id' => ['required', 'string', 'starts_with:gen_', 'max:255'],
            'status' => ['required', Rule::in(['queued', 'processing', 'succeeded', 'failed'])],
            'metadata.local_id' => ['required', Rule::in([$localId])],
            'rates' => ['required', 'array'],
            'completed_at' => ['required_if:status,succeeded,failed', 'nullable', 'date'],
            'usage' => ['required_if:status,succeeded', 'nullable', 'array'],
            'image_url' => ['required_if:status,succeeded', 'nullable', 'url:http,https'],
            'image_url_expires_at' => ['nullable', 'date'],
        ])->validate();

        DB::transaction(function () use ($localId, $object) {
            $g = Generation::lockForUpdate()->findOrFail($localId);
            if ($g->provider_id && $g->provider_id !== $object['id']) {
                throw new \UnexpectedValueException('Provider generation identity mismatch.');
            }
            if ($g->isTerminal()) {
                return;
            }
            $status = $object['status'];
            if ($g->status === 'processing' && $status === 'queued') {
                return;
            }
            $g->fill([
                'provider_id' => $object['id'], 'status' => $status, 'rates' => $object['rates'],
                'slot_reserved' => ! in_array($status, ['succeeded', 'failed'], true),
                'last_checked_at' => now(), 'consecutive_errors' => 0,
                'error_code' => null, 'error_message' => null,
                'started_at' => $object['started_at'] ?? $g->started_at,
                'next_attempt_at' => now()->addSeconds(15),
            ]);
            if ($status === 'succeeded') {
                $usage = $object['usage'];
                $cost = Money::cost($usage, $object['rates']);
                if (($usage['total_tokens'] ?? null) !== array_sum(array_intersect_key($usage, array_flip(Money::TOKENS)))) {
                    throw new \UnexpectedValueException('Provider token total is inconsistent.');
                }
                $charge = Money::charge($cost);
                $g->fill([
                    'usage' => $usage, 'actual_provider_cost' => $cost, 'actual_customer_price' => $charge,
                    'image_url' => $object['image_url'], 'image_url_expires_at' => $object['image_url_expires_at'] ?? null,
                ]);
                // Plain INSERT, not upsert: ledger entries cannot be silently replaced.
                DB::table('generation_charges')->insert([
                    'generation_id' => $g->id, 'user_id' => $g->user_id,
                    'provider_cost' => $cost, 'customer_charge' => $charge,
                    'usage' => json_encode($usage, JSON_THROW_ON_ERROR), 'rates' => json_encode($object['rates'], JSON_THROW_ON_ERROR),
                    'completed_at' => Carbon::parse($object['completed_at'])->utc(), 'created_at' => now(),
                ]);
            }
            if ($status === 'failed') {
                $g->fill(['actual_provider_cost' => '0', 'actual_customer_price' => '0',
                    'error_code' => $object['error']['code'] ?? 'generation_failed',
                    'error_message' => mb_substr($object['error']['message'] ?? 'Generation failed. You were not charged.', 0, 2000)]);
            }
            if (in_array($status, ['succeeded', 'failed'], true)) {
                $g->completed_at = Carbon::parse($object['completed_at'])->utc();
                $g->next_attempt_at = null;
            }
            $g->save();
        }, 5);
    }
}
