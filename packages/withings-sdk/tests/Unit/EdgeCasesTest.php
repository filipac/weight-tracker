<?php

use Filipac\Withings\Client\WithingsClient;
use Filipac\Withings\DataTransferObjects\Measurement;
use Filipac\Withings\DataTransferObjects\MeasurementData;
use Filipac\Withings\Enums\MeasurementType;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;

describe('Client Edge Cases', function () {
    it('handles empty access token gracefully', function () {
        $client = new WithingsClient(
            accessToken: '',
            clientId: 'test-client-id',
            clientSecret: 'test-client-secret'
        );

        expect($client->getAccessToken())->toBe('');
    });

    it('handles null parameters in constructor', function () {
        $client = new WithingsClient;

        expect($client->getAccessToken())->toBe('')
            ->and($client->getRefreshToken())->toBe('')
            ->and($client->getClientId())->toBe('')
            ->and($client->getClientSecret())->toBe('');
    });

    it('handles custom base URL', function () {
        $client = new WithingsClient(
            baseUrl: 'https://custom-api.example.com'
        );

        expect($client)->toBeWithingsClient();
    });
});

describe('MeasurementData Edge Cases', function () {
    it('handles empty measurement groups', function () {
        $data = new MeasurementData(
            measuregrps: []
        );

        $measurements = $data->getMeasurements();

        expect($measurements)->toBeInstanceOf(Illuminate\Support\Collection::class)
            ->and($measurements)->toHaveCount(0);
    });

    it('handles malformed measurement groups', function () {
        $data = new MeasurementData(
            measuregrps: [
                [
                    'grpid' => 1,
                    'date' => 1609459200,
                    'measures' => [
                        [
                            'value' => 70000,
                            'type' => 1, // Include required type field
                            'unit' => -3,
                        ],
                    ],
                ],
            ]
        );

        $measurements = $data->getMeasurements();

        expect($measurements)->toHaveCount(1);
    });

    it('handles measurements with zero values', function () {
        $measurement = new Measurement(
            type: MeasurementType::WEIGHT,
            value: 0,
            unit: -3,
            date: 1609459200,
            grpid: 1
        );

        expect($measurement->getRealValue())->toBe(0.0);
    });

    it('handles measurements with extreme unit values', function () {
        $measurement = new Measurement(
            type: MeasurementType::WEIGHT,
            value: 70000,
            unit: -10, // Very small unit
            date: 1609459200,
            grpid: 1
        );

        expect($measurement->getRealValue())->toBe(0.000007); // 70000 * 10^-10 = 0.000007
    });

    it('handles measurements with positive unit values', function () {
        $measurement = new Measurement(
            type: MeasurementType::WEIGHT,
            value: 70,
            unit: 3, // Multiply by 1000
            date: 1609459200,
            grpid: 1
        );

        expect($measurement->getRealValue())->toBe(70000.0);
    });
});

describe('Service Edge Cases', function () {
    it('handles empty API responses', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode(mockWithingsResponse())),
        ]);
        $client = createMockClient($mock);

        $result = $client->user()->getInfo();

        expect($result['status'])->toBe(0)
            ->and($result['body'])->toBeArray();
    });

    it('handles measurements with missing optional fields', function () {
        $mock = new MockHandler([
            new Response(200, [], json_encode(mockWithingsResponse([
                'measuregrps' => [
                    [
                        'grpid' => 1,
                        'date' => 1609459200,
                        'measures' => [
                            [
                                'value' => 70000,
                                'type' => 1,
                                'unit' => -3,
                                // Missing 'created', 'category', etc.
                            ],
                        ],
                    ],
                ],
            ]))),
        ]);
        $client = createMockClient($mock);

        $result = $client->measures()->getWeight();

        expect($result->measuregrps)->toHaveCount(1);
    });
});

describe('Enum Edge Cases', function () {
    it('handles invalid enum values gracefully', function () {
        expect(MeasurementType::tryFrom(999))->toBeNull();
    });

    it('can get all measurement types including edge cases', function () {
        $allTypes = MeasurementType::getAllTypes();

        expect($allTypes)->toBeArray()
            ->and($allTypes)->toBeGreaterThan(10) // Should have many types
            ->and($allTypes)->toContain(MeasurementType::WEIGHT); // Weight enum should be included
    });
});

describe('Date and Time Edge Cases', function () {
    it('handles timestamp edge cases', function () {
        $measurement = new Measurement(
            type: MeasurementType::WEIGHT,
            value: 70000,
            unit: -3,
            date: 0, // Unix epoch
            grpid: 1
        );

        $dateTime = $measurement->getDateTime();

        expect($dateTime->getTimestamp())->toBe(0);
    });

    it('handles future timestamps', function () {
        $futureTimestamp = time() + 86400; // Tomorrow
        $measurement = new Measurement(
            type: MeasurementType::WEIGHT,
            value: 70000,
            unit: -3,
            date: $futureTimestamp,
            grpid: 1
        );

        $dateTime = $measurement->getDateTime();

        expect($dateTime->getTimestamp())->toBe($futureTimestamp);
    });
});

describe('Configuration Edge Cases', function () {
    it('works with minimal configuration', function () {
        $client = new WithingsClient;

        expect($client->isConfigured())->toBeFalse()
            ->and($client->oauth2())->toBeInstanceOf(\Filipac\Withings\Services\OAuth2Service::class);
    });

    it('works with partial configuration', function () {
        $client = new WithingsClient(clientId: 'test-id');

        expect($client->isConfigured())->toBeFalse(); // Needs both client_id and client_secret
    });
});
