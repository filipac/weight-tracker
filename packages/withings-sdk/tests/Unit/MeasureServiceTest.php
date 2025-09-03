<?php

use Filipac\Withings\Client\WithingsClient;
use Filipac\Withings\DataTransferObjects\MeasurementData;
use Filipac\Withings\DataTransferObjects\Parameters\GetMeasuresParams;
use Filipac\Withings\Enums\MeasurementType;
use Filipac\Withings\Services\MeasureService;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

it('can create measure service instance', function () {
    $client = new WithingsClient(
        accessToken: 'test-access-token',
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    expect($client->measures())->toBeInstanceOf(MeasureService::class);
});

it('can get weight measurements', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse([
            'measuregrps' => [
                [
                    'grpid' => 1,
                    'attrib' => 0,
                    'date' => 1609459200,
                    'created' => 1609459200,
                    'category' => 1,
                    'measures' => [
                        [
                            'value' => 70000,
                            'type' => 1,
                            'unit' => -3,
                        ],
                    ],
                ],
            ],
        ]))),
    ]);

    $client = createMockClient($mock);
    $result = $client->measures()->getWeight();

    expect($result)->toBeInstanceOf(MeasurementData::class)
        ->and($result->status)->toBe(0)
        ->and($result->measuregrps)->toHaveCount(1);
});

it('can get height measurements', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse([
            'measuregrps' => [
                [
                    'grpid' => 1,
                    'attrib' => 0,
                    'date' => 1609459200,
                    'created' => 1609459200,
                    'category' => 1,
                    'measures' => [
                        [
                            'value' => 180,
                            'type' => 4,
                            'unit' => -2,
                        ],
                    ],
                ],
            ],
        ]))),
    ]);

    $client = createMockClient($mock);
    $result = $client->measures()->getHeight();

    expect($result)->toBeInstanceOf(MeasurementData::class)
        ->and($result->status)->toBe(0)
        ->and($result->measuregrps)->toHaveCount(1);
});

it('can get measures with custom parameters', function () {
    $mock = new MockHandler([
        new Response(200, [], json_encode(mockWithingsResponse([
            'measuregrps' => [],
        ]))),
    ]);

    $client = createMockClient($mock);

    $params = new GetMeasuresParams(
        meastype: MeasurementType::WEIGHT,
        startdate: 1609459200,
        enddate: 1609545600
    );

    $result = $client->measures()->getMeasures($params);

    expect($result)->toBeInstanceOf(MeasurementData::class)
        ->and($result->status)->toBe(0);
});
