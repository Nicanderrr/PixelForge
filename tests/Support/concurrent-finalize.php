<?php

use App\Jobs\ProcessWebhook;
use App\Services\FinalizeGeneration;
use Illuminate\Contracts\Console\Kernel;

// Runs only against the dedicated test DB, from ConcurrencyTest. No provider requests.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.connections.mysql.database') !== 'forma_test') {
    throw new RuntimeException('Dedicated test database required.');
}
$eventId = $argv[1];
$start = $argv[2];
while (microtime(true) < (float) $start) {
    usleep(10000);
}
(new ProcessWebhook($eventId))->handle(app(FinalizeGeneration::class));
echo "Processed\n";
