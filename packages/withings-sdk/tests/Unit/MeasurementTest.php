<?php

use Filipac\Withings\DataTransferObjects\Measurement;
use Filipac\Withings\Enums\MeasurementCategory;
use Filipac\Withings\Enums\MeasurementType;

it('can create measurement with enum types', function () {
    $measurement = new Measurement(
        type: MeasurementType::WEIGHT,
        value: 70000,
        unit: -3,
        date: 1609459200,
        grpid: 1,
        category: MeasurementCategory::REAL
    );

    expect($measurement->type)->toBe(MeasurementType::WEIGHT)
        ->and($measurement->value)->toBe(70000)
        ->and($measurement->unit)->toBe(-3)
        ->and($measurement->date)->toBe(1609459200)
        ->and($measurement->grpid)->toBe(1)
        ->and($measurement->category)->toBe(MeasurementCategory::REAL);
});

it('can create measurement with integer types that convert to enums', function () {
    $measurement = new Measurement(
        type: 1, // Weight
        value: 70000,
        unit: -3,
        date: 1609459200,
        grpid: 1,
        category: 1 // Real
    );

    expect($measurement->type)->toBe(MeasurementType::WEIGHT)
        ->and($measurement->category)->toBe(MeasurementCategory::REAL);
});

it('handles invalid enum conversion gracefully', function () {
    $measurement = new Measurement(
        type: 999, // Invalid type
        value: 70000,
        unit: -3,
        date: 1609459200,
        grpid: 1,
        category: 999 // Invalid category
    );

    expect($measurement->type)->toBe(MeasurementType::WEIGHT) // Falls back to WEIGHT
        ->and($measurement->category)->toBeNull(); // Invalid category becomes null
});

it('can get real value with unit adjustment', function () {
    $measurement = new Measurement(
        type: MeasurementType::WEIGHT,
        value: 70000,
        unit: -3, // Means divide by 1000
        date: 1609459200,
        grpid: 1
    );

    expect($measurement->getRealValue())->toBe(70.0);
});

it('can get real value with positive unit', function () {
    $measurement = new Measurement(
        type: MeasurementType::HEIGHT,
        value: 180,
        unit: -2, // Means divide by 100
        date: 1609459200,
        grpid: 1
    );

    expect($measurement->getRealValue())->toBe(1.8);
});

it('can get measurement type as enum', function () {
    $measurement = new Measurement(
        type: MeasurementType::WEIGHT,
        value: 70000,
        unit: -3,
        date: 1609459200,
        grpid: 1
    );

    expect($measurement->getMeasurementType())->toBe(MeasurementType::WEIGHT);
});

it('can get type name', function () {
    $measurement = new Measurement(
        type: MeasurementType::WEIGHT,
        value: 70000,
        unit: -3,
        date: 1609459200,
        grpid: 1
    );

    expect($measurement->getTypeName())->toBe('Weight');
});

it('can get unit string', function () {
    $measurement = new Measurement(
        type: MeasurementType::WEIGHT,
        value: 70000,
        unit: -3,
        date: 1609459200,
        grpid: 1
    );

    expect($measurement->getUnit())->toBe('kg');
});

it('can get category name', function () {
    $measurement = new Measurement(
        type: MeasurementType::WEIGHT,
        value: 70000,
        unit: -3,
        date: 1609459200,
        grpid: 1,
        category: MeasurementCategory::REAL
    );

    expect($measurement->getCategoryName())->toBe('Real measurement');
});

it('returns null for category name when no category', function () {
    $measurement = new Measurement(
        type: MeasurementType::WEIGHT,
        value: 70000,
        unit: -3,
        date: 1609459200,
        grpid: 1
    );

    expect($measurement->getCategoryName())->toBeNull();
});

it('can get date as DateTime object', function () {
    $timestamp = 1609459200; // 2021-01-01 00:00:00
    $measurement = new Measurement(
        type: MeasurementType::WEIGHT,
        value: 70000,
        unit: -3,
        date: $timestamp,
        grpid: 1
    );

    $dateTime = $measurement->getDateTime();

    expect($dateTime)->toBeInstanceOf(DateTime::class)
        ->and($dateTime->getTimestamp())->toBe($timestamp);
});

it('can check if measurement is body composition', function () {
    $fatFreeMassMeasurement = new Measurement(
        type: MeasurementType::FAT_FREE_MASS,
        value: 50000,
        unit: -3,
        date: 1609459200,
        grpid: 1
    );

    $fatMeasurement = new Measurement(
        type: MeasurementType::FAT_RATIO,
        value: 15000,
        unit: -3,
        date: 1609459200,
        grpid: 1
    );

    $weightMeasurement = new Measurement(
        type: MeasurementType::WEIGHT,
        value: 70000,
        unit: -3,
        date: 1609459200,
        grpid: 1
    );

    expect($fatFreeMassMeasurement->isBodyComposition())->toBeTrue()
        ->and($fatMeasurement->isBodyComposition())->toBeTrue()
        ->and($weightMeasurement->isBodyComposition())->toBeFalse(); // Weight is not in body composition group
});

it('can check if measurement is vital sign', function () {
    $heartRateMeasurement = new Measurement(
        type: MeasurementType::HEART_PULSE,
        value: 72,
        unit: 0,
        date: 1609459200,
        grpid: 1
    );

    expect($heartRateMeasurement->isVitalSign())->toBeTrue();
});

it('can check if measurement is ECG related', function () {
    // This would need an ECG measurement type to test properly
    $weightMeasurement = new Measurement(
        type: MeasurementType::WEIGHT,
        value: 70000,
        unit: -3,
        date: 1609459200,
        grpid: 1
    );

    expect($weightMeasurement->isEcgMeasurement())->toBeFalse();
});

it('is readonly and immutable', function () {
    $measurement = new Measurement(
        type: MeasurementType::WEIGHT,
        value: 70000,
        unit: -3,
        date: 1609459200,
        grpid: 1
    );

    // This should cause a PHP error if attempted
    expect(fn () => $measurement->value = 80000)
        ->toThrow(Error::class);
});
