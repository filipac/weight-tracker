<?php

use Filipac\Withings\Enums\MeasurementType;

it('has correct weight measurement type', function () {
    expect(MeasurementType::WEIGHT->value)->toBe(1);
});

it('has correct height measurement type', function () {
    expect(MeasurementType::HEIGHT->value)->toBe(4);
});

it('can get measurement type from value', function () {
    expect(MeasurementType::from(1))->toBe(MeasurementType::WEIGHT)
        ->and(MeasurementType::from(4))->toBe(MeasurementType::HEIGHT);
});

it('can get all measurement types', function () {
    $types = MeasurementType::cases();

    expect($types)->toBeArray()
        ->and($types)->toContain(MeasurementType::WEIGHT)
        ->and($types)->toContain(MeasurementType::HEIGHT);
});
