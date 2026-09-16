<?php

namespace App\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

class OuraClient
{
    public function configured(): bool
    {
        return (bool) (config('health.oura.client_id') && config('health.oura.client_secret'));
    }

    public function tokens(): ?array
    {
        $encrypted = Cache::get('health:oura:tokens');

        if (! $encrypted) {
            return null;
        }
        $tokens = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
        $tokens['scopes'] = self::normalizeScopes($tokens['scopes'] ?? []);

        return $tokens;
    }

    public static function normalizeScopes(array|string $scopes): array
    {
        $scopes = is_string($scopes) ? preg_split('/\s+/', trim($scopes)) : $scopes;

        return array_values(array_unique(array_filter(array_map(
            fn ($scope) => is_string($scope) ? preg_replace('/^extapi:/', '', trim($scope)) : '',
            $scopes,
        ))));
    }

    public function save(array $response, array $scopes): void
    {
        if (empty($response['access_token']) || empty($response['refresh_token']) || empty($response['expires_in'])) {
            throw new ProviderException('error', 'Oura returned an incomplete token response. Please reconnect.');
        }
        Cache::forever('health:oura:tokens', Crypt::encryptString(json_encode([
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'],
            'expires_at' => now()->timestamp + (int) $response['expires_in'],
            'scopes' => self::normalizeScopes($scopes),
        ], JSON_THROW_ON_ERROR)));
    }

    public function exchange(array $parameters): array
    {
        try {
            $response = Http::asForm()->timeout(15)->connectTimeout(5)->post('https://api.ouraring.com/oauth/token', $parameters + [
                'client_id' => config('health.oura.client_id'),
                'client_secret' => config('health.oura.client_secret'),
            ]);
        } catch (\Throwable) {
            throw new ProviderException('error', 'Oura authorization could not be reached. Reconnect if the token was already exchanged.');
        }
        if (! $response->successful()) {
            if (in_array($response->json('error'), ['invalid_grant', 'invalid_token'])) {
                Cache::forget('health:oura:tokens');
            }
            throw new ProviderException('reconnect', 'Oura authorization failed. Please reconnect Oura.');
        }

        return $response->json();
    }

    public function accessToken(): string
    {
        $tokens = $this->tokens();
        if ($tokens && $tokens['expires_at'] > now()->timestamp + 60) {
            return $tokens['access_token'];
        }

        // Other fetches must wait long enough for the 15-second token request.
        return Cache::lock('health:oura:refresh', 30)->block(20, function () {
            $tokens = $this->tokens();
            if (! $tokens) {
                throw new ProviderException('reconnect', 'Connect Oura to fetch your data.');
            }
            if ($tokens['expires_at'] <= now()->timestamp + 60) {
                $response = $this->exchange(['grant_type' => 'refresh_token', 'refresh_token' => $tokens['refresh_token']]);
                $this->save($response, isset($response['scope']) ? self::normalizeScopes($response['scope']) : $tokens['scopes']);
                $tokens = $this->tokens();
            }

            return $tokens['access_token'];
        });
    }

    public function collection(string $endpoint, array $parameters, string $scope): array
    {
        $tokens = $this->tokens();
        if (! $tokens) {
            throw new ProviderException('reconnect', 'Connect Oura to fetch your data.');
        }
        if (! in_array($scope, $tokens['scopes'], true)) {
            throw new ProviderException('permission', "Oura permission '{$scope}' is missing. Reconnect and grant this permission.");
        }
        $data = [];
        $next = null;
        $seen = [];
        do {
            $response = Http::withToken($this->accessToken())->timeout(15)->connectTimeout(5)
                ->get('https://api.ouraring.com/v2/usercollection/'.$endpoint, $parameters + ($next ? ['next_token' => $next] : []));
            if ($response->status() === 401) {
                throw new ProviderException('reconnect', 'Oura authorization was rejected. Please reconnect.');
            }
            if ($response->status() === 403) {
                throw new ProviderException('permission', 'This Oura collection is unavailable for the granted permissions or membership.');
            }
            if ($response->status() === 429) {
                throw new ProviderException('rate_limited', 'Oura rate limit reached. Please try again later.');
            }
            if (! $response->successful() || ! is_array($response->json('data'))) {
                throw new ProviderException('error', 'Oura could not return this collection. Please retry.');
            }
            $data = array_merge($data, $response->json('data'));
            $next = $response->json('next_token');
            if ($next && isset($seen[$next])) {
                throw new ProviderException('error', 'Oura repeated a pagination token. Please retry.');
            }
            if ($next) {
                $seen[$next] = true;
            }
        } while ($next && count($seen) < 100);
        if ($next) {
            throw new ProviderException('error', 'Oura collection exceeded the pagination limit.');
        }

        return $data;
    }
}
