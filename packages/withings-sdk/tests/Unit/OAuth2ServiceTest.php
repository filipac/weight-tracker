<?php

use Filipac\Withings\Client\WithingsClient;
use Filipac\Withings\Services\OAuth2Service;

it('can generate authorization url', function () {
    $client = new WithingsClient(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    $oauth2 = $client->oauth2();
    $url = $oauth2->getAuthorizationUrl(
        redirectUri: 'https://example.com/callback',
        state: 'test-state',
        scopes: ['user.info']
    );

    expect($url)
        ->toContain('https://account.withings.com/oauth2_user/authorize2')
        ->toContain('client_id=test-client-id')
        ->toContain('redirect_uri=https%3A%2F%2Fexample.com%2Fcallback')
        ->toContain('scope=user.info')
        ->toContain('state=test-state')
        ->toContain('response_type=code');
});

it('can generate authorization url with default scopes', function () {
    $client = new WithingsClient(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    $oauth2 = $client->oauth2();
    $url = $oauth2->getAuthorizationUrl('https://example.com/callback');

    expect($url)
        ->toContain('https://account.withings.com/oauth2_user/authorize2')
        ->toContain('client_id=test-client-id')
        ->toContain('redirect_uri=https%3A%2F%2Fexample.com%2Fcallback')
        ->toContain('scope=user.info%2Cuser.metrics')
        ->toContain('response_type=code');
});

it('creates oauth2 service instance', function () {
    $client = new WithingsClient(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    expect($client->oauth2())->toBeInstanceOf(OAuth2Service::class);
});

it('checks if client is configured', function () {
    $configuredClient = new WithingsClient(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    $unconfiguredClient = new WithingsClient;

    expect($configuredClient->isConfigured())->toBeTrue()
        ->and($unconfiguredClient->isConfigured())->toBeFalse();
});
