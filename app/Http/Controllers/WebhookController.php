<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWebhook;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WebhookController extends Controller
{
    public function __invoke(Request $request)
    {
        $secret = config('pixelforge.webhook_secret');
        abort_unless(is_string($secret) && $secret !== '', 503, 'Webhook not configured.');
        $raw = $request->getContent();
        abort_if(strlen($raw) > 131072, 413);
        $header = (string) $request->header('Pixelforge-Signature');
        abort_unless(preg_match('/^t=(\d{1,12}),v1=([a-f0-9]{64})$/D', $header, $parts), 400, 'Invalid signature.');
        $timestamp = $parts[1];
        $expected = hash_hmac('sha256', $timestamp.'.'.$raw, $secret);
        abort_unless(abs(time() - (int) $timestamp) <= 300 && hash_equals($expected, $parts[2]), 400, 'Invalid signature.');
        try {
            $event = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            abort(400, 'Invalid JSON.');
        }
        abort_unless(is_array($event), 400);
        $validator = Validator::make($event, [
            'id' => ['required', 'string', 'starts_with:evt_', 'max:255'],
            'type' => ['required', Rule::in(['ping', 'generation.processing', 'generation.succeeded', 'generation.failed'])],
            'data.object' => ['required_unless:type,ping', 'array'],
        ]);
        abort_if($validator->fails(), 400, 'Invalid event envelope.');
        if ($event['type'] !== 'ping') {
            abort_unless(($event['data']['object']['status'] ?? null) === substr($event['type'], strlen('generation.')), 400);
        }
        foreach (['Pixelforge-Event-Id' => 'id', 'Pixelforge-Event-Type' => 'type'] as $headerName => $key) {
            abort_if($request->hasHeader($headerName) && $request->header($headerName) !== $event[$key], 400);
        }
        // Persist before acknowledgment. If dispatch fails, provider retry or scheduler recovers the inbox.
        $hash = hash('sha256', $raw);
        DB::table('webhook_events')->insertOrIgnore([
            'id' => $event['id'], 'type' => $event['type'], 'payload' => $raw, 'body_hash' => $hash,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        abort_unless(DB::table('webhook_events')->where('id', $event['id'])->value('body_hash') === $hash, 409, 'Event identity conflict.');
        ProcessWebhook::dispatch($event['id']);

        return response()->noContent();
    }
}
