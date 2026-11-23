<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\RequestGeneration;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PixelForgeSmoke extends Command
{
    protected $signature = 'pixelforge:smoke {email : Existing local studio user email} {--include-errors : Also exercise blocked and overloaded create errors}';

    protected $description = 'Queue real simulated-provider contract scenarios for an existing user (requires API key)';

    public function handle(RequestGeneration $service): int
    {
        if (! config('pixelforge.api_key')) {
            $this->error('Set PIXELFORGE_API_KEY in .env first.');

            return self::FAILURE;
        }
        $user = User::where('email', $this->argument('email'))->first();
        if (! $user) {
            $this->error('Register this user in the studio first.');

            return self::FAILURE;
        }
        $prompts = ['A violet ceramic vase [succeed] [duplicate]', 'A failed experiment [fail]', 'A slow sunrise [slow] [succeed]'];
        if ($this->option('include-errors')) {
            $prompts = [...$prompts, 'A blocked request [blocked]', 'A busy studio [overloaded]'];
        }
        foreach ($prompts as $prompt) {
            $g = $service->create($user, ['prompt' => $prompt, 'size' => '1024x1024', 'quality' => 'low', 'reference_images' => [], 'request_key' => (string) Str::uuid()]);
            $this->line($g->id.' '.$prompt);
        }
        $this->info('Real requests queued. Keep worker and scheduler running; inspect history and webhook deliveries, then run pixelforge:reconcile for the UTC completion date.');

        return self::SUCCESS;
    }
}
