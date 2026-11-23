<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\ProviderFixtures;
use Tests\TestCase;

/** Real separate PHP processes and InnoDB locks, not an SQLite approximation. */
class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations, ProviderFixtures;

    public function test_simultaneous_webhooks_and_duplicates_create_exactly_one_charge(): void
    {
        $g = $this->generation();
        $first = $this->event($g);
        $second = $this->event($g);
        $old = $this->event($g, 'processing');
        foreach ([$first, $second, $old] as $event) {
            $raw = json_encode($event, JSON_THROW_ON_ERROR);
            DB::table('webhook_events')->insert(['id' => $event['id'], 'type' => $event['type'], 'payload' => $raw,
                'body_hash' => hash('sha256', $raw), 'created_at' => now(), 'updated_at' => now()]);
        }
        $start = (string) (microtime(true) + 2);
        $processes = [];
        foreach ([$first['id'], $first['id'], $second['id'], $old['id']] as $id) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/concurrent-finalize.php'), $id, $start], base_path(), [
                'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'forma_test',
                'DB_HOST' => config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'),
                'DB_USERNAME' => config('database.connections.mysql.username'), 'DB_PASSWORD' => config('database.connections.mysql.password'),
            ]);
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
        }
        $this->assertSame('succeeded', $g->fresh()->status);
        $this->assertDatabaseCount('generation_charges', 1);
        $this->assertSame(3, DB::table('webhook_events')->whereNotNull('processed_at')->count());
        $this->assertSame('0.381887500000000000', DB::table('generation_charges')->value('customer_charge'));
    }
}
