<?php

namespace Filipac\Withings\Services;

class OAuth2Service extends BaseService
{
    /**
     * Exchange authorization code for access token
     */
    public function getAccessToken(string $code, string $redirectUri): array
    {
        return $this->client->post('/v2/oauth2', [
            'action' => 'requesttoken',
            'grant_type' => 'authorization_code',
            'client_id' => $this->client->getClientId(),
            'client_secret' => $this->client->getClientSecret(),
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);
    }

    /**
     * Refresh access token using refresh token
     */
    public function refreshToken(): array
    {
        $response = $this->client->post('/v2/oauth2', [
            'action' => 'requesttoken',
            'grant_type' => 'refresh_token',
            'client_id' => $this->client->getClientId(),
            'client_secret' => $this->client->getClientSecret(),
            'refresh_token' => $this->client->getRefreshToken(),
        ]);

        // Update the client with new tokens
        if (isset($response['body']['access_token'])) {
            $this->client->setAccessToken($response['body']['access_token']);
        }

        if (isset($response['body']['refresh_token'])) {
            $this->client->setRefreshToken($response['body']['refresh_token']);
        }

        return $response;
    }

    /**
     * Build authorization URL
     */
    public function getAuthorizationUrl(
        string $redirectUri,
        ?string $state = null,
        array $scopes = ['user.info', 'user.metrics']
    ): string {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->client->getClientId(),
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $scopes),
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return 'https://account.withings.com/oauth2_user/authorize2?'.http_build_query($params);
    }

    public function generateState(): string
    {
        $request = request();
        $request->session()->put('state', $state = $this->getState());

        return $state;
    }

    /**
     * Get the string used for session state.
     *
     * @return string
     */
    protected function getState()
    {
        return \Illuminate\Support\Str::random(40);
    }
}
