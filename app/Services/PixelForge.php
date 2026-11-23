<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class PixelForge
{
    public function fingerprint(): string
    {
        return hash('sha256', (string) config('pixelforge.api_key'));
    }

    public function scope(): string
    {
        return 'pixelforge:'.config('pixelforge.account_scope');
    }

    public function pricing(): array
    {
        return Cache::remember('pixelforge:pricing', 300, function () {
            $data = Http::connectTimeout(5)->timeout(15)->acceptJson()
                ->get(config('pixelforge.base_url').'/pricing')->throw()->json();
            if (($data['currency'] ?? '') !== 'usd' || ($data['unit'] ?? '') !== 'per_1m_tokens') {
                throw new \UnexpectedValueException('Unsupported provider pricing currency or unit.');
            }
            Money::cost(array_fill_keys(Money::TOKENS, 1), $data['rates']);

            return $data;
        });
    }

    public function request(string $method, string $path, array $data = [], ?string $idempotencyKey = null): array
    {
        if (! config('pixelforge.api_key')) {
            throw new ProviderException('missing_api_key', false);
        }
        $scope = $this->scope();
        $cooldown = max(0, (int) Cache::get($scope.':cooldown', 0) - time());
        if ($cooldown || RateLimiter::tooManyAttempts($scope.':requests', config('pixelforge.requests_per_minute'))) {
            throw new ProviderException('local_rate_limit', true, max($cooldown, RateLimiter::availableIn($scope.':requests'), 1));
        }
        RateLimiter::hit($scope.':requests', 60);
        try {
            $client = Http::withToken(config('pixelforge.api_key'))->acceptJson()->asJson()->connectTimeout(5)->timeout(20);
            if ($idempotencyKey) {
                $client = $client->withHeaders(['Idempotency-Key' => $idempotencyKey]);
            }
            $response = $client->send($method, config('pixelforge.base_url').$path, [$method === 'GET' ? 'query' : 'json' => $data]);
        } catch (ConnectionException) {
            throw new ProviderException('network_timeout', true, 0, $method === 'POST');
        }
        if (! $response->successful()) {
            $code = (string) $response->json('error.code', 'provider_http_'.$response->status());
            $retryAfter = max(0, (int) $response->header('Retry-After'));
            $retry = in_array($code, ['rate_limit_exceeded', 'concurrency_limit_exceeded', 'overloaded'], true)
                || $response->serverError();
            if ($retryAfter) {
                Cache::put($scope.':cooldown', time() + $retryAfter, $retryAfter);
            }
            throw new ProviderException($code, $retry, $retryAfter, $method === 'POST' && $response->serverError() && $code !== 'overloaded');
        }
        $data = $response->json();
        if (! is_array($data)) {
            throw new ProviderException('invalid_response', true, 0, $method === 'POST');
        }

        return $data;
    }
}
