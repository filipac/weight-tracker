<?php

use Filipac\Withings\Client\WithingsClient;
use Filipac\Withings\Services\HeartService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

it('can create heart service instance', function () {
    $client = new WithingsClient(
        accessToken: 'test-access-token',
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    expect($client->heart())->toBeInstanceOf(HeartService::class);
});

it('can get heart rate measurements', function () {
    $mockData = [
        'series' => [
            [
                'deviceid' => 'abc123',
                'model' => 16,
                'ecg' => [
                    'signalid' => 12345,
                    'afib' => 0,
                ],
                'heart_rate' => [
                    [
                        'timestamp' => 1609459200,
                        'value' => 72,
                    ],
                ],
            ],
        ],
    ];

    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse($mockData))),
    ]);
    $client = createMockClient($mock);

    $result = $client->heart()->getHeartRate();

    expect($result['status'])->toBe(0)
        ->and($result['body']['series'])->toHaveCount(1)
        ->and($result['body']['series'][0]['heart_rate'][0]['value'])->toBe(72);
});

it('can list heart rate measurements with date range', function () {
    $mockData = [
        'series' => [
            [
                'deviceid' => 'abc123',
                'heart_rate' => [
                    [
                        'timestamp' => 1609459200,
                        'value' => 75,
                    ],
                    [
                        'timestamp' => 1609462800,
                        'value' => 68,
                    ],
                ],
            ],
        ],
    ];

    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse($mockData))),
    ]);
    $client = createMockClient($mock);

    $result = $client->heart()->list(
        startdate: 1609459200,
        enddate: 1609545600
    );

    expect($result['status'])->toBe(0)
        ->and($result['body']['series'][0]['heart_rate'])->toHaveCount(2);
});
