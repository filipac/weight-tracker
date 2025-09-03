<?php

use Filipac\Withings\Client\WithingsClient;
use Filipac\Withings\DataTransferObjects\Measurement;
use Filipac\Withings\DataTransferObjects\MeasurementData;
use Filipac\Withings\Enums\MeasurementCategory;
use Filipac\Withings\Enums\MeasurementType;

it('can handle large measurement datasets efficiently', function () {
    $startTime = microtime(true);

    // Create a large dataset
    $measuregrps = [];
    for ($i = 0; $i < 1000; $i++) {
        $measuregrps[] = [
            'grpid' => $i,
            'attrib' => 0,
            'date' => 1609459200 + ($i * 3600),
            'created' => 1609459200 + ($i * 3600),
            'category' => 1,
            'measures' => [
                [
                    'value' => 70000 + ($i * 10),
                    'type' => 1,
                    'unit' => -3,
                ],
            ],
        ];
    }

    $data = new MeasurementData(measuregrps: $measuregrps);
    $measurements = $data->getMeasurements();

    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;

    expect($measurements)->toHaveCount(1000)
        ->and($executionTime)->toBeLessThan(1.0); // Should process 1000 measurements in under 1 second
});

it('can create many measurement objects quickly', function () {
    $startTime = microtime(true);

    $measurements = [];
    for ($i = 0; $i < 10000; $i++) {
        $measurements[] = new Measurement(
            type: MeasurementType::WEIGHT,
            value: 70000 + $i,
            unit: -3,
            date: 1609459200 + $i,
            grpid: $i,
            category: MeasurementCategory::REAL
        );
    }

    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;

    expect(count($measurements))->toBe(10000)
        ->and($executionTime)->toBeLessThan(2.0); // Should create 10k objects in under 2 seconds
});

it('can filter large datasets efficiently', function () {
    // Create measurement data with mixed types
    $measuregrps = [];
    for ($i = 0; $i < 5000; $i++) {
        $measuregrps[] = [
            'grpid' => $i,
            'date' => 1609459200 + ($i * 3600),
            'measures' => [
                [
                    'value' => 70000 + ($i * 10),
                    'type' => $i % 2 === 0 ? 1 : 4, // Alternate between weight and height
                    'unit' => -3,
                ],
            ],
        ];
    }

    $data = new MeasurementData(measuregrps: $measuregrps);

    $startTime = microtime(true);
    $weightMeasurements = $data->getMeasurementsByType(MeasurementType::WEIGHT);
    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;

    expect($weightMeasurements)->toHaveCount(2500) // Half should be weight
        ->and($executionTime)->toBeLessThan(0.5); // Should filter quickly
});

it('can calculate real values for many measurements efficiently', function () {
    $measurements = [];
    for ($i = 0; $i < 1000; $i++) {
        $measurements[] = new Measurement(
            type: MeasurementType::WEIGHT,
            value: 70000 + ($i * 100),
            unit: -3,
            date: 1609459200 + $i,
            grpid: $i
        );
    }

    $startTime = microtime(true);
    $realValues = array_map(fn ($m) => $m->getRealValue(), $measurements);
    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;

    expect(count($realValues))->toBe(1000)
        ->and($executionTime)->toBeLessThan(0.1); // Should calculate very quickly
});

it('memory usage stays reasonable with large datasets', function () {
    $initialMemory = memory_get_usage();

    // Create large dataset
    $measuregrps = [];
    for ($i = 0; $i < 5000; $i++) {
        $measuregrps[] = [
            'grpid' => $i,
            'date' => 1609459200 + ($i * 3600),
            'measures' => [
                [
                    'value' => 70000 + ($i * 10),
                    'type' => 1,
                    'unit' => -3,
                ],
            ],
        ];
    }

    $data = new MeasurementData(measuregrps: $measuregrps);
    $measurements = $data->getMeasurements();

    $finalMemory = memory_get_usage();
    $memoryUsed = $finalMemory - $initialMemory;

    expect($measurements)->toHaveCount(5000)
        ->and($memoryUsed)->toBeLessThan(50 * 1024 * 1024); // Should use less than 50MB
});

it('can handle concurrent service instantiation', function () {
    $client = new WithingsClient(
        accessToken: 'test-access-token',
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret'
    );

    $startTime = microtime(true);

    // Create many service instances
    $services = [];
    for ($i = 0; $i < 1000; $i++) {
        $services[] = [
            'measures' => $client->measures(),
            'user' => $client->user(),
            'heart' => $client->heart(),
            'oauth2' => $client->oauth2(),
            'notifications' => $client->notifications(),
        ];
    }

    $endTime = microtime(true);
    $executionTime = $endTime - $startTime;

    expect(count($services))->toBe(1000)
        ->and($executionTime)->toBeLessThan(1.0); // Should create services quickly
});
