<?php

namespace App\Actions;

use Illuminate\Support\Facades\Http;

class RefreshWithingsAction
{
    public function execute()
    {
        $withings = cache()->get('withings');
        $accessToken = $withings['access_token'];
        $refreshToken = $withings['refresh_token'];
        $expiresIn = $withings['expires_in'];

        $url = 'https://wbsapi.withings.net/v2/oauth2';
        $response = Http::post($url, [
            'action' => 'requesttoken',
            'grant_type' => 'refresh_token',
            'client_id' => config('services.withings.client_id'),
            'client_secret' => config('services.withings.client_secret'),
            'refresh_token' => $refreshToken,
        ]);

        $resp = $response->json();

        if (empty($resp['body']['access_token'])) {
            dd($resp);
        }

        cache()->forever('withings', [
            'access_token' => $resp['body']['access_token'],
            'refresh_token' => $resp['body']['refresh_token'],
            'expires_in' => $resp['body']['expires_in'],
        ]);
    }
}
