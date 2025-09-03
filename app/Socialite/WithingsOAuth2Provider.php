<?php

namespace App\Socialite;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class WithingsOAuth2Provider extends AbstractProvider implements ProviderInterface
{
    /**
     * The scopes being requested.
     *
     * @var array
     */
    protected $scopes = ['user.info', 'user.metrics'];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ',';

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://account.withings.com/oauth2_user/authorize2', $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return 'https://wbsapi.withings.net/v2/oauth2';
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->post('https://wbsapi.withings.net/v2/user', [
            'form_params' => [
                'action' => 'getdevice',
            ],
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        $data = json_decode($response->getBody(), true);

        // Return the body content if available, otherwise return the full response
        return $data['body'] ?? $data;
    }

    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id' => $user['userid'] ?? $user['id'] ?? null,
            'nickname' => null,
            'name' => isset($user['firstname'], $user['lastname'])
                ? trim($user['firstname'].' '.$user['lastname'])
                : ($user['name'] ?? null),
            'email' => $user['email'] ?? null,
            'avatar' => $user['avatar'] ?? null,
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'action' => 'requesttoken',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getAccessTokenResponse($code)
    {
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'form_params' => $this->getTokenFields($code),
        ]);

        $data = json_decode($response->getBody(), true);

        return $data['body'] ?? $data;
    }
}
