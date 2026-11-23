<?php

namespace Tests\Feature;

use App\Jobs\AdvanceGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\Pricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ProviderFixtures;
use Tests\TestCase;

class GenerationTest extends TestCase
{
    use ProviderFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['pixelforge.api_key' => 'test-key']);
        Http::preventStrayRequests();
        $this->fakeHttp(['*/pricing' => Http::response($this->pricing())]);
        Queue::fake();
    }

    private function input(): array
    {
        return ['prompt' => 'A lighthouse', 'quality' => 'low', 'size' => '1024x1024', 'reference_images' => [], 'request_key' => (string) Str::uuid()];
    }

    public function test_submission_is_durable_and_duplicate_browser_requests_reuse_generation(): void
    {
        $this->actingAs(User::factory()->create());
        $data = $this->input();
        $this->post('/generations', $data)->assertRedirect();
        $this->post('/generations', $data)->assertRedirect();
        $this->assertDatabaseCount('generations', 1);
        Queue::assertPushed(AdvanceGeneration::class, 1);
        $g = Generation::first();
        $this->assertSame($g->id, $g->request_payload['metadata']['local_id']);
        $this->assertSame('queued', $g->status);
        $data['prompt'] = 'Different parameters';
        $this->post('/generations', $data)->assertSessionHasErrors('prompt');
    }

    public function test_validation_rejects_unsupported_options_and_reference_schemes(): void
    {
        $data = $this->input();
        $data['size'] = '512x512';
        $data['quality'] = 'ultra';
        $data['reference_images'] = ['file:///secret'];
        $this->actingAs(User::factory()->create())->post('/generations', $data)->assertSessionHasErrors(['size', 'quality', 'reference_images.0']);
        $data = $this->input();
        $data['prompt'] = str_repeat('x', 4001);
        $data['reference_images'] = array_fill(0, 5, 'https://example.test/image.png');
        $this->post('/generations', $data)->assertSessionHasErrors(['prompt', 'reference_images']);
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_owners_are_isolated_and_secrets_are_not_serialized(): void
    {
        $g = $this->generation();
        $other = $this->generation();
        $this->actingAs($g->user)->get('/generations/'.$other->id)->assertNotFound();
        $this->get('/generations')->assertInertia(fn (Assert $page) => $page->component('History')->has('generations.data', 1)->where('generations.data.0.id', $g->id)->missing('generations.data.0.request_payload')->missing('generations.data.0.provider_key_fingerprint'));
    }

    public function test_live_estimate_uses_unicode_and_maximum_reasoning(): void
    {
        $quote = app(Pricing::class)->estimate(str_repeat('🌿', 400), '1536x1024', 'high', ['https://example.test/a', 'https://example.test/b']);
        $this->assertSame('0.467343750000000000', $quote['maximum_customer_price']);
        $this->assertSame('0.330050000000000000', $quote['provider_cost']);
    }

    public function test_pricing_outage_or_missing_key_prevents_unpriced_submission(): void
    {
        $this->actingAs(User::factory()->create());
        config(['pixelforge.api_key' => null]);
        $this->post('/generations', $this->input())->assertSessionHasErrors('prompt');
        config(['pixelforge.api_key' => 'test-key']);
        $this->fakeHttp(['*/pricing' => Http::response([], 503)]);
        $this->post('/generations', $this->input())->assertSessionHasErrors('prompt');
        $this->assertDatabaseCount('generations', 0);
    }
}
