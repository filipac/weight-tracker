<?php

use Filipac\Withings\DataTransferObjects\MeasurementData;
use Filipac\Withings\Enums\MeasurementType;

it('can create measurement data with constructor parameters', function () {
    $data = new MeasurementData(
        status: 0,
        measuregrps: [
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
        offset: 0,
        more: false,
        timezone: 'Europe/Paris'
    );

    expect($data->status)->toBe(0)
        ->and($data->measuregrps)->toHaveCount(1)
        ->and($data->offset)->toBe(0)
        ->and($data->more)->toBeFalse()
        ->and($data->timezone)->toBe('Europe/Paris');
});

it('can create measurement data from api response array', function () {
    $responseData = [
        'status' => 0,
        'body' => [
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
            'offset' => 0,
            'more' => 0,
            'timezone' => 'Europe/Paris',
        ],
    ];

    $data = new MeasurementData(data: $responseData);

    expect($data->status)->toBe(0)
        ->and($data->measuregrps)->toHaveCount(1)
        ->and($data->offset)->toBe(0)
        ->and($data->more)->toBeFalse()
        ->and($data->timezone)->toBe('Europe/Paris');
});

it('can get measurements as collection', function () {
    $data = new MeasurementData(
        measuregrps: [
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
        ]
    );

    $measurements = $data->getMeasurements();

    expect($measurements)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($measurements)->toHaveCount(1);
});

it('filters measurements by type', function () {
    $data = new MeasurementData(
        measuregrps: [
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
                    [
                        'value' => 180,
                        'type' => 4,
                        'unit' => -2,
                    ],
                ],
            ],
        ]
    );

    $weightMeasurements = $data->getMeasurementsByType(MeasurementType::WEIGHT);
    $heightMeasurements = $data->getMeasurementsByType(MeasurementType::HEIGHT);

    expect($weightMeasurements)->toHaveCount(1)
        ->and($heightMeasurements)->toHaveCount(1);
});
