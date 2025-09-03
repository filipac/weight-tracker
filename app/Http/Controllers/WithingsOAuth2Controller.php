<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class WithingsOAuth2Controller extends Controller
{
    /**
     * Redirect to Withings OAuth2 authorization page
     */
    public function redirect()
    {
        return Socialite::driver('withings')->redirect();
    }

    /**
     * Handle the OAuth2 callback from Withings
     */
    public function callback(Request $request)
    {
        try {
            $user = Socialite::driver('withings')->user();

            cache()->forever('withings', [
                'access_token' => $user->token,
                'refresh_token' => $user->refreshToken,
                'expires_in' => $user->expiresIn,
            ]);

            // For now, just dump the raw response to see the actual structure
            dd([
                'access_token' => $user->token,
                'refresh_token' => $user->refreshToken,
                'expires_in' => $user->expiresIn,
                'raw_response' => $user->getRaw(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'OAuth callback failed',
                'message' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }
}
