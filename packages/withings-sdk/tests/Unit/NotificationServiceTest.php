<?php

use Filipac\Withings\Client\WithingsClient;
use Filipac\Withings\Enums\NotificationApplication;
use Filipac\Withings\Services\NotificationService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

it('can create notification service instance', function () {
    $client = new WithingsClient(
        accessToken: 'test-access-token',
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    expect($client->notifications())->toBeInstanceOf(NotificationService::class);
});

it('can subscribe to notifications', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse())),
    ]);
    $client = createMockClient($mock);

    $result = $client->notifications()->subscribe(
        'https://example.com/webhook',
        NotificationApplication::WEIGHT,
        'Test subscription'
    );

    expect($result['status'])->toBe(0);
});

it('can revoke notifications', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse())),
    ]);
    $client = createMockClient($mock);

    $result = $client->notifications()->revoke(
        'https://example.com/webhook',
        NotificationApplication::WEIGHT
    );

    expect($result['status'])->toBe(0);
});

it('can get notification info', function () {
    $mockData = [
        'profiles' => [
            [
                'appli' => 1,
                'callbackurl' => 'https://example.com/webhook',
                'expires' => 2147483647,
                'comment' => 'Test notification',
            ],
        ],
    ];

    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse($mockData))),
    ]);
    $client = createMockClient($mock);

    $result = $client->notifications()->get(NotificationApplication::WEIGHT);

    expect($result['status'])->toBe(0)
        ->and($result['body']['profiles'])->toHaveCount(1)
        ->and($result['body']['profiles'][0]['appli'])->toBe(1);
});

it('can list all notifications', function () {
    $mockData = [
        'profiles' => [
            [
                'appli' => 1,
                'callbackurl' => 'https://example.com/webhook',
                'expires' => 2147483647,
                'comment' => 'Weight notifications',
            ],
            [
                'appli' => 4,
                'callbackurl' => 'https://example.com/sleep-webhook',
                'expires' => 2147483647,
                'comment' => 'Sleep notifications',
            ],
        ],
    ];

    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse($mockData))),
    ]);
    $client = createMockClient($mock);

    $result = $client->notifications()->list();

    expect($result['status'])->toBe(0)
        ->and($result['body']['profiles'])->toHaveCount(2)
        ->and($result['body']['profiles'][0]['appli'])->toBe(1)
        ->and($result['body']['profiles'][1]['appli'])->toBe(4);
});
