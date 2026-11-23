<?php

namespace Tests\Support;

use App\Models\Generation;
use App\Models\User;
use App\Services\PixelForge;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Documented contract fixtures; never used by production code. */
trait ProviderFixtures
{
    protected function fakeHttp(array $responses = []): void
    {
        // Laravel merges successive fake maps; replace the factory when changing scenarios.
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($responses);
    }

    protected function pricing(): array
    {
        return ['object' => 'pricing', 'currency' => 'usd', 'unit' => 'per_1m_tokens',
            'rates' => ['input_text_tokens' => '5.00', 'input_image_tokens' => '10.00', 'output_tokens' => '40.00', 'reasoning_tokens' => '12.50'],
            'tokens' => ['characters_per_text_token' => 4, 'text_token_variance' => 0.15, 'per_reference_image' => 765,
                'output' => ['low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                    'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                    'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450]],
                'reasoning' => ['low' => ['min' => 0, 'max' => 500], 'medium' => ['min' => 200, 'max' => 2000], 'high' => ['min' => 1000, 'max' => 8000]]]];
    }

    protected function generation(array $attributes = []): Generation
    {
        $id = (string) Str::uuid();

        return Generation::create(array_merge([
            'id' => $id, 'user_id' => User::factory()->create()->id, 'request_key' => (string) Str::uuid(),
            'provider_key_fingerprint' => app(PixelForge::class)->fingerprint(),
            'prompt' => 'A lighthouse [succeed]', 'size' => '1024x1024', 'quality' => 'low', 'reference_images' => [],
            'request_payload' => ['prompt' => 'A lighthouse [succeed]', 'size' => '1024x1024', 'quality' => 'low', 'reference_images' => [], 'metadata' => ['local_id' => $id]],
            'estimated_provider_cost' => '0.02', 'estimated_customer_price' => '0.025', 'maximum_customer_price' => '0.03',
            'estimated_rates' => $this->pricing()['rates'], 'next_attempt_at' => now(), 'status' => 'queued',
        ], $attributes));
    }

    protected function snapshot(Generation $g, string $status = 'succeeded'): array
    {
        return array_merge($g->request_payload, ['id' => $g->provider_id ?? 'gen_'.$g->id, 'object' => 'generation',
            'status' => $status, 'rates' => $this->pricing()['rates'], 'created_at' => now()->toIso8601String(),
            'started_at' => now()->toIso8601String(), 'completed_at' => in_array($status, ['succeeded', 'failed']) ? now()->toIso8601String() : null,
            'image_url' => $status === 'succeeded' ? 'https://pixelforge.promptedit.dev/images/test.png?signature=test' : null,
            'image_url_expires_at' => $status === 'succeeded' ? now()->addDay()->toIso8601String() : null,
            'usage' => $status === 'succeeded' ? ['input_text_tokens' => 12, 'input_image_tokens' => 765, 'output_tokens' => 6450, 'reasoning_tokens' => 3184, 'total_tokens' => 10411] : null,
            'error' => $status === 'failed' ? ['code' => 'generation_failed', 'message' => 'Generation failed. You were not charged.'] : null]);
    }

    protected function event(Generation $g, string $status = 'succeeded', ?string $id = null): array
    {
        return ['id' => $id ?? 'evt_'.Str::uuid(), 'object' => 'event', 'type' => 'generation.'.$status,
            'created_at' => now()->toIso8601String(), 'data' => ['object' => $this->snapshot($g, $status)]];
    }

    protected function signedPost(array $event, ?int $time = null, string $secret = 'whsec_test')
    {
        $raw = json_encode($event, JSON_THROW_ON_ERROR);
        $timestamp = $time ?? time();

        return $this->call('POST', '/webhooks/pixelforge', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
            'HTTP_PIXELFORGE_SIGNATURE' => 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$raw, $secret),
        ], $raw);
    }
}
