<?php

use Filipac\Withings\Client\WithingsClient;
use Filipac\Withings\Services\MeasureService;
use Filipac\Withings\Services\OAuth2Service;
use Filipac\Withings\Services\UserService;

it('can create a withings client instance', function () {
    $client = new WithingsClient(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    expect($client)->toBeWithingsClient();
});

it('can access oauth2 service', function () {
    $client = new WithingsClient(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    expect($client->oauth2())->toBeInstanceOf(OAuth2Service::class);
});

it('can access measures service', function () {
    $client = new WithingsClient(
        accessToken: 'test-access-token',
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    expect($client->measures())->toBeInstanceOf(MeasureService::class);
});

it('can access user service', function () {
    $client = new WithingsClient(
        accessToken: 'test-access-token',
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    expect($client->user())->toBeInstanceOf(UserService::class);
});

it('can set and get access token', function () {
    $client = new WithingsClient(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    $client->setAccessToken('new-access-token');

    expect($client->getAccessToken())->toBe('new-access-token');
});

it('can set and get refresh token', function () {
    $client = new WithingsClient(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    $client->setRefreshToken('new-refresh-token');

    expect($client->getRefreshToken())->toBe('new-refresh-token');
});

it('can set and get HTTP client', function () {
    $client = new WithingsClient(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    $originalHttpClient = $client->getHttpClient();
    $newHttpClient = new \GuzzleHttp\Client(['timeout' => 60]);

    $client->setHttpClient($newHttpClient);

    expect($client->getHttpClient())->toBe($newHttpClient)
        ->and($client->getHttpClient())->not->toBe($originalHttpClient);
});
