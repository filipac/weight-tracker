<?php

namespace App\Http\Controllers\Health;

use App\Health\OuraClient;
use App\Health\ProviderException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OuraController
{
    public function redirect(Request $request, OuraClient $client)
    {
        abort_unless($client->configured(), 503, 'Configure OURA_CLIENT_ID and OURA_CLIENT_SECRET first.');
        $state = Str::random(64);
        $request->session()->put('health.oura_state', ['value' => $state, 'expires_at' => now()->addMinutes(10)->timestamp, 'scopes' => config('health.oura.scopes')]);

        return redirect()->away('https://cloud.ouraring.com/oauth/authorize?'.http_build_query([
            'response_type' => 'code', 'client_id' => config('health.oura.client_id'),
            'redirect_uri' => $this->callbackUrl(), 'scope' => implode(' ', config('health.oura.scopes')), 'state' => $state,
        ]));
    }

    public function callback(Request $request, OuraClient $client)
    {
        $state = $request->session()->pull('health.oura_state');
        abort_unless($state && $state['expires_at'] >= now()->timestamp && is_string($request->state) && hash_equals($state['value'], $request->state), 419, 'Oura login expired or state did not match. Start again.');
        if ($request->has('error')) {
            return redirect('/')->with('message', 'Oura access was not granted. You can reconnect when ready.');
        }
        $request->validate(['code' => 'required|string|max:2048', 'scope' => 'nullable|string|max:512']);
        try {
            $tokens = $client->exchange([
                'grant_type' => 'authorization_code', 'code' => $request->code, 'redirect_uri' => $this->callbackUrl(),
            ]);
            // OAuth servers can return granted scopes with the token or callback. If
            // omitted from both, OAuth 2.0 defines them as the requested scopes.
            $scope = $tokens['scope'] ?? $request->input('scope');
            $scopes = $scope !== null ? OuraClient::normalizeScopes($scope) : ($state['scopes'] ?? config('health.oura.scopes'));
            $client->save($tokens, $scopes);
        } catch (ProviderException $e) {
            return redirect('/')->with('message', $e->getMessage());
        }

        return redirect('/')->with('message', 'Oura connected. You can now preview your health journal.');
    }

    private function callbackUrl(): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim(config('health.oura.redirect_path'), '/');
    }
}
