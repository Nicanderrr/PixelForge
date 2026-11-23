<?php

use App\Jobs\AdvanceGeneration;
use App\Jobs\ProcessWebhook;
use App\Models\Generation;
use App\Services\PixelForge;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('pixelforge:recover', function () {
    Generation::whereNotIn('status', ['succeeded', 'failed', 'attention'])->where('next_attempt_at', '<=', now())
        ->orderBy('next_attempt_at')->limit(100)->pluck('id')->each(fn ($id) => AdvanceGeneration::dispatch($id));
    DB::table('webhook_events')->whereNull('processed_at')->whereNull('failed_at')->where('created_at', '>=', now()->subDay())
        ->orderBy('created_at')->limit(100)->pluck('id')->each(fn ($id) => ProcessWebhook::dispatch($id));
})->purpose('Recover due generation work and durable webhook inbox events');

Artisan::command('pixelforge:retry {generation}', function () {
    return DB::transaction(function () {
        $g = Generation::lockForUpdate()->findOrFail($this->argument('generation'));
        if ($g->isTerminal()) {
            $this->error('Terminal generations cannot be retried. Create a new request instead.');

            return 1;
        }
        if ($g->provider_key_fingerprint !== app(PixelForge::class)->fingerprint()) {
            $this->error('Restore the original API key first.');

            return 1;
        }
        $g->update(['status' => $g->provider_id ? 'processing' : 'submitting', 'consecutive_errors' => 0,
            'next_attempt_at' => now(), 'recovery_until' => now()->addDay(), 'error_code' => null, 'error_message' => null]);
        AdvanceGeneration::dispatch($g->id)->afterCommit();
        $this->info('Recovery queued with the original idempotency key.');

        return 0;
    }, 5);
})->purpose('Explicit operator recovery; never creates a new provider identity');

Artisan::command('pixelforge:reconcile {start} {end}', function () {
    $start = $this->argument('start');
    $end = $this->argument('end');
    validator(compact('start', 'end'), ['start' => 'required|date_format:Y-m-d', 'end' => 'required|date_format:Y-m-d|after_or_equal:start'])->validate();
    if (Carbon::parse($start)->diffInDays(Carbon::parse($end)) > 91) {
        $this->error('Maximum range is 92 inclusive UTC days.');

        return 1;
    }
    $provider = app(PixelForge::class);
    $report = $provider->request('GET', '/usage', ['start_date' => $start, 'end_date' => $end]);
    $mismatch = false;
    $rows = [];
    foreach ($report['data'] as $day) {
        $local = (string) DB::table('generation_charges')->join('generations', 'generations.id', '=', 'generation_charges.generation_id')
            ->where('provider_key_fingerprint', $provider->fingerprint())->whereDate('generation_charges.completed_at', $day['date'])->sum('provider_cost');
        $delta = bcsub($local, $day['cost_usd'], 18);
        if (bccomp($delta, '0', 18) !== 0) {
            $mismatch = true;
        }
        $rows[] = [$day['date'], $local, $day['cost_usd'], $delta];
    }
    $this->table(['UTC completion day', 'Local provider USD', 'PixelForge USD', 'Difference'], $rows);
    $this->info($mismatch ? 'Mismatch: inspect incomplete requests, key scope, and external generations.' : 'Exact reconciliation passed.');

    return $mismatch ? 1 : 0;
})->purpose('Compare finalized provider costs against GET /v1/usage');

Schedule::command('pixelforge:recover')->everyTenSeconds()->withoutOverlapping();
