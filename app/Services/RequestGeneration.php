<?php

namespace App\Services;

use App\Jobs\AdvanceGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RequestGeneration
{
    public function __construct(private Pricing $pricing, private PixelForge $provider) {}

    /** Input has already passed GenerationRequest validation. */
    public function create(User $user, array $input): Generation
    {
        if (! config('pixelforge.api_key')) {
            throw ValidationException::withMessages(['prompt' => 'Generation is unavailable until the studio is connected. Please try again later.']);
        }
        $existing = Generation::where('user_id', $user->id)->where('request_key', $input['request_key'])->first();
        if ($existing) {
            return $this->sameRequest($existing, $input);
        }
        try {
            $estimate = $this->pricing->estimate($input['prompt'], $input['size'], $input['quality'], $input['reference_images']);
        } catch (\Throwable $e) {
            report($e);
            throw ValidationException::withMessages(['prompt' => 'Live pricing is unavailable. No request was submitted. Please try again.']);
        }
        $g = DB::transaction(function () use ($user, $input, $estimate) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $existing = Generation::where('user_id', $user->id)->where('request_key', $input['request_key'])->first();
            if ($existing) {
                return $this->sameRequest($existing, $input);
            }
            $id = (string) Str::uuid();

            return Generation::create([
                'id' => $id, 'user_id' => $user->id, 'request_key' => $input['request_key'],
                'provider_key_fingerprint' => $this->provider->fingerprint(), 'prompt' => $input['prompt'],
                'size' => $input['size'], 'quality' => $input['quality'], 'reference_images' => $input['reference_images'],
                'request_payload' => ['prompt' => $input['prompt'], 'size' => $input['size'], 'quality' => $input['quality'],
                    'reference_images' => $input['reference_images'], 'metadata' => ['local_id' => $id]],
                'estimated_provider_cost' => $estimate['provider_cost'], 'estimated_customer_price' => $estimate['customer_price'],
                'maximum_customer_price' => $estimate['maximum_customer_price'], 'estimated_rates' => $estimate['rates'],
                'status' => 'queued', 'next_attempt_at' => now(),
            ]);
        }, 5);
        if ($g->wasRecentlyCreated) {
            AdvanceGeneration::dispatch($g->id);
        }

        return $g;
    }

    private function sameRequest(Generation $g, array $input): Generation
    {
        foreach (['prompt', 'size', 'quality', 'reference_images'] as $field) {
            if ($input[$field] !== $g->$field) {
                throw ValidationException::withMessages(['prompt' => 'This submission identifier was already used. Open a new generation form to submit a different request.']);
            }
        }

        return $g;
    }
}
