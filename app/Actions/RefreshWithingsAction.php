<?php

namespace App\Actions;

use Filipac\Withings\Facades\Withings;

class RefreshWithingsAction
{
    public function execute()
    {
        $resp = Withings::oauth2()->refreshToken();

        if ($resp['status'] !== 0) {
            throw new \Exception('Withings OAuth2 authentication failed - status mismatch');
        }

        cache()->forever('withings', [
            'access_token' => $resp['body']['access_token'],
            'refresh_token' => $resp['body']['refresh_token'],
            'expires_in' => $resp['body']['expires_in'],
        ]);
    }
}
