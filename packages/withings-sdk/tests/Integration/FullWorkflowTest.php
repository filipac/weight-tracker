<?php

use Filipac\Withings\Client\WithingsClient;
use Filipac\Withings\DataTransferObjects\MeasurementData;
use Filipac\Withings\Enums\NotificationApplication;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

it('can perform complete oauth flow', function () {
    $client = new WithingsClient(
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    // Step 1: Generate authorization URL
    $authUrl = $client->oauth2()->getAuthorizationUrl(
        'https://example.com/callback',
        'test-state'
    );

    expect($authUrl)->toContain('https://account.withings.com/oauth2_user/authorize2');

    // Step 2: Mock token exchange
    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]))),
    ]);

    $clientWithMock = createMockClient($mock);
    $tokenResponse = $clientWithMock->oauth2()->getAccessToken('auth-code', 'https://example.com/callback');

    expect($tokenResponse['body']['access_token'])->toBe('new-access-token');
});

it('can perform complete data retrieval workflow', function () {
    // Mock multiple API calls for a complete workflow
    $mock = new MockHandler([
        // User info call
        new Response(200, [], json_encode(mockWithingsResponse([
            'users' => [
                [
                    'userid' => 123456,
                    'firstname' => 'John',
                    'timezone' => 'Europe/Paris',
                ],
            ],
        ]))),
        // Measurements call
        new Response(200, [], json_encode(mockWithingsResponse([
            'measuregrps' => [
                [
                    'grpid' => 1,
                    'date' => 1609459200,
                    'measures' => [
                        ['value' => 70000, 'type' => 1, 'unit' => -3],
                    ],
                ],
            ],
        ]))),
        // Devices call
        new Response(200, [], json_encode(mockWithingsResponse([
            'devices' => [
                [
                    'type' => 'Body Cardio',
                    'deviceid' => 'abc123',
                ],
            ],
        ]))),
    ]);
    $client = createMockClient($mock);

    // Complete workflow
    $userInfo = $client->user()->getInfo();
    $measurements = $client->measures()->getWeight();
    $devices = $client->user()->getDevices();

    expect($userInfo['body']['users'][0]['firstname'])->toBe('John')
        ->and($measurements)->toBeInstanceOf(MeasurementData::class)
        ->and($measurements->measuregrps)->toHaveCount(1)
        ->and($devices['body']['devices'][0]['type'])->toBe('Body Cardio');
});

it('can handle notification subscription workflow', function () {
    $mock = new MockHandler([
        // Subscribe
        new Response(200, [], json_encode(mockWithingsResponse())),
        // List notifications
        new Response(200, [], json_encode(mockWithingsResponse([
            'profiles' => [
                [
                    'appli' => 1,
                    'callbackurl' => 'https://example.com/webhook',
                ],
            ],
        ]))),
        // Revoke
        new Response(200, [], json_encode(mockWithingsResponse())),
    ]);
    $client = createMockClient($mock);

    // Notification workflow
    $subscribe = $client->notifications()->subscribe('https://example.com/webhook', NotificationApplication::WEIGHT);
    $list = $client->notifications()->list();
    $revoke = $client->notifications()->revoke('https://example.com/webhook', NotificationApplication::WEIGHT);

    expect($subscribe['status'])->toBe(0)
        ->and($list['body']['profiles'])->toHaveCount(1)
        ->and($revoke['status'])->toBe(0);
});

it('can handle token refresh workflow', function () {
    $mock = new MockHandler([
        // Initial API call fails with 401
        new Response(200, [], json_encode([
            'status' => 401,
        ])),
        // Token refresh succeeds
        new Response(200, [], json_encode(mockWithingsResponse([
            'access_token' => 'refreshed-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ]))),
        // Retry original call succeeds
        new Response(200, [], json_encode(mockWithingsResponse([
            'users' => [['userid' => 123456]],
        ]))),
    ]);
    $client = createMockClient($mock);

    try {
        $client->user()->getInfo();
    } catch (Exception $e) {
        // First call should fail
        expect($e->getMessage())->toContain('Unauthorized');
    }

    // Refresh token
    $refreshResponse = $client->oauth2()->refreshToken();

    expect($refreshResponse['body']['access_token'])->toBe('refreshed-access-token')
        ->and($client->getAccessToken())->toBe('refreshed-access-token');
});
