<?php

namespace App\Http\Controllers;

use Filipac\Withings\Facades\Withings;
use Illuminate\Http\Request;

class WithingsOAuth2Controller extends Controller
{
    /**
     * Redirect to Withings OAuth2 authorization page
     */
    public function redirect()
    {
        return redirect(Withings::oauth2()->getAuthorizationUrl(
            redirectUri: config('services.withings.redirect'),
            scopes: ['user.info', 'user.metrics'],
            state: Withings::oauth2()->generateState()
        ));
    }

    /**
     * Handle the OAuth2 callback from Withings
     */
    public function callback(Request $request)
    {
        try {
            $state = $request->session()->pull('state');

            if ($state !== $request->state) {
                throw new \Exception('Withings OAuth2 authentication failed - state mismatch');
            }

            $resp = Withings::oauth2()->getAccessToken($request->code, config('services.withings.redirect'));

            if ($resp['status'] !== 0) {
                throw new \Exception('Withings OAuth2 authentication failed - status mismatch');
            }

            cache()->forever('withings', [
                'access_token' => $resp['body']['access_token'],
                'refresh_token' => $resp['body']['refresh_token'],
                'expires_in' => $resp['body']['expires_in'],
            ]);

            return redirect()->route('weight.index');

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'OAuth callback failed',
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }
}
