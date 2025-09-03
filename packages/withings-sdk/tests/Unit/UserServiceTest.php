<?php

use Filipac\Withings\Client\WithingsClient;
use Filipac\Withings\Services\UserService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

it('can create user service instance', function () {
    $client = new WithingsClient(
        accessToken: 'test-access-token',
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    expect($client->user())->toBeInstanceOf(UserService::class);
});

it('can get user info', function () {
    $mockUserData = [
        'users' => [
            [
                'userid' => 123456,
                'firstname' => 'John',
                'lastname' => 'Doe',
                'shortname' => 'John D.',
                'timezone' => 'Europe/Paris',
                'created' => 1609459200,
            ],
        ],
    ];

    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse($mockUserData))),
    ]);

    $client = createMockClient($mock);

    $result = $client->user()->getInfo();

    expect($result['status'])->toBe(0)
        ->and($result['body']['users'])->toHaveCount(1)
        ->and($result['body']['users'][0]['userid'])->toBe(123456)
        ->and($result['body']['users'][0]['firstname'])->toBe('John');
});

it('can get user devices', function () {
    $mockDevices = [
        'devices' => [
            [
                'type' => 'Body Cardio',
                'model' => 'Withings Body Cardio',
                'battery' => 'high',
                'deviceid' => 'abc123',
                'timezone' => 'Europe/Paris',
                'last_session_date' => 1609459200,
            ],
        ],
    ];

    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse($mockDevices))),
    ]);
    $client = createMockClient($mock);

    $result = $client->user()->getDevices();

    expect($result['status'])->toBe(0)
        ->and($result['body']['devices'])->toHaveCount(1)
        ->and($result['body']['devices'][0]['type'])->toBe('Body Cardio');
});

it('can get user goals', function () {
    $mockGoals = [
        'goals' => [
            [
                'type' => 1,
                'value' => 75000,
                'unit' => -3,
            ],
        ],
    ];

    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse($mockGoals))),
    ]);
    $client = createMockClient($mock);

    $result = $client->user()->getGoals();

    expect($result['status'])->toBe(0)
        ->and($result['body']['goals'])->toHaveCount(1)
        ->and($result['body']['goals'][0]['type'])->toBe(1);
});

it('can get user timezone', function () {
    $mockData = [
        'users' => [
            [
                'timezone' => 'Europe/Paris',
            ],
        ],
    ];

    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse($mockData))),
    ]);
    $client = createMockClient($mock);

    $result = $client->user()->getTimezone();

    expect($result['status'])->toBe(0)
        ->and($result['body']['users'][0]['timezone'])->toBe('Europe/Paris');
});

it('can get user preferences', function () {
    $mockPrefs = [
        'prefs' => [
            'unit_pref' => [
                'unit' => 1,
            ],
        ],
    ];

    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse($mockPrefs))),
    ]);
    $client = createMockClient($mock);

    $result = $client->user()->getPreferences();

    expect($result['status'])->toBe(0)
        ->and($result['body']['prefs'])->toHaveKey('unit_pref');
});
