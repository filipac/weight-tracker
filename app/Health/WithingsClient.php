<?php

namespace App\Health;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class WithingsClient
{
    public function token(): string
    {
        $tokens = Cache::get('withings');
        if (! empty($tokens['refresh_token']) && ($tokens['expires_at'] ?? 0) > now()->timestamp + 60) {
            return $tokens['access_token'];
        }

        return Cache::lock('health:withings:refresh', 30)->block(20, function () {
            $tokens = Cache::get('withings');
            if (empty($tokens['refresh_token'])) {
                throw new ProviderException('reconnect', 'Connect Withings to fetch your data.');
            }
            if (($tokens['expires_at'] ?? 0) <= now()->timestamp + 60) {
                $response = Http::asForm()->timeout(15)->connectTimeout(5)->post('https://wbsapi.withings.net/v2/oauth2', [
                    'action' => 'requesttoken', 'grant_type' => 'refresh_token',
                    'client_id' => config('services.withings.client_id'), 'client_secret' => config('services.withings.client_secret'),
                    'refresh_token' => $tokens['refresh_token'],
                ]);
                $this->checkRateLimit($response);
                $body = $response->json('body');
                if (! $response->successful() || $response->json('status') !== 0 || empty($body['access_token']) || empty($body['refresh_token'])) {
                    throw new ProviderException('reconnect', 'Withings authorization failed. Please reconnect.');
                }
                $tokens = array_merge($tokens, array_intersect_key($body, array_flip(['access_token', 'refresh_token', 'expires_in'])), ['expires_at' => now()->timestamp + (int) $body['expires_in']]);
                Cache::forever('withings', $tokens);
            }

            return $tokens['access_token'];
        });
    }

    public function request(string $path, array $parameters): array
    {
        $response = Http::asForm()->withToken($this->token())->timeout(15)->connectTimeout(5)
            ->post('https://wbsapi.withings.net/'.$path, $parameters);
        $this->checkRateLimit($response);
        if ($response->status() === 401 || in_array($response->json('status'), [401, 503])) {
            throw new ProviderException('reconnect', 'Reconnect Withings to renew access.');
        }
        if ($response->status() === 403 || in_array($response->json('status'), [403])) {
            throw new ProviderException('permission', 'Withings permission is missing. Reconnect with activity access.');
        }
        if (! $response->successful() || $response->json('status') !== 0 || ! is_array($response->json('body'))) {
            throw new ProviderException('error', 'Withings could not return this collection. Please retry.');
        }

        return $response->json('body');
    }

    private function checkRateLimit(Response $response): void
    {
        if ($response->status() === 429 || (int) $response->json('status') === 601) {
            $header = trim($response->header('Retry-After'));
            $retryAfter = ctype_digit($header) ? (int) $header : (($at = strtotime($header)) !== false ? max(0, $at - now()->timestamp) : null);
            throw new ProviderException('rate_limited', 'Withings rate limit reached. Retry later.', $retryAfter);
        }
    }

    public function collection(string $path, array $parameters, string $field): array
    {
        $rows = [];
        $offset = null;
        $seen = [];
        do {
            $body = $this->request($path, $parameters + ($offset !== null ? ['offset' => $offset] : []));
            if (! is_array($body[$field] ?? null)) {
                throw new ProviderException('error', 'Withings returned an unexpected collection.');
            }
            $rows = array_merge($rows, $body[$field]);
            if (empty($body['more'])) {
                return $rows;
            }
            $offset = $body['offset'] ?? null;
            if ($offset === null || isset($seen[$offset])) {
                throw new ProviderException('error', 'Withings pagination could not be completed.');
            }
            $seen[$offset] = true;
        } while (count($seen) < 100);
        throw new ProviderException('error', 'Withings collection exceeded the pagination limit.');
    }
}
