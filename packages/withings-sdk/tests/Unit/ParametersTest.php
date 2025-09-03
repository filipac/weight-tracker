<?php

use Filipac\Withings\DataTransferObjects\Parameters\GetMeasuresParams;
use Filipac\Withings\DataTransferObjects\Parameters\NotificationParams;
use Filipac\Withings\Enums\MeasurementCategory;
use Filipac\Withings\Enums\MeasurementType;
use Filipac\Withings\Enums\NotificationApplication;

describe('GetMeasuresParams', function () {
    it('can create basic parameters', function () {
        $params = new GetMeasuresParams(
            meastype: MeasurementType::WEIGHT,
            startdate: 1609459200,
            enddate: 1609545600
        );

        $array = $params->toArray();

        expect($array['action'])->toBe('getmeas')
            ->and($array['meastype'])->toBe(1)
            ->and($array['startdate'])->toBe(1609459200)
            ->and($array['enddate'])->toBe(1609545600);
    });

    it('can create parameters for weight measurements', function () {
        $params = GetMeasuresParams::forWeight(1609459200, 1609545600);

        $array = $params->toArray();

        expect($array['action'])->toBe('getmeas')
            ->and($array['meastype'])->toBe(1); // Weight type
    });

    it('can create parameters with category filter', function () {
        $params = new GetMeasuresParams(
            meastype: MeasurementType::WEIGHT,
            category: MeasurementCategory::REAL->value
        );

        $array = $params->toArray();

        expect($array['category'])->toBe(1);
    });

    it('excludes null parameters from array', function () {
        $params = new GetMeasuresParams(
            meastype: MeasurementType::WEIGHT
            // startdate and enddate are null
        );

        $array = $params->toArray();

        expect($array)->not->toHaveKey('startdate')
            ->and($array)->not->toHaveKey('enddate')
            ->and($array)->toHaveKey('meastype');
    });
});

describe('NotificationParams', function () {
    it('can create subscription parameters', function () {
        $params = NotificationParams::subscribe(
            'https://example.com/webhook',
            NotificationApplication::WEIGHT,
            'Test subscription'
        );

        $array = $params->toArray();

        expect($array['action'])->toBe('subscribe')
            ->and($array['callbackurl'])->toBe('https://example.com/webhook')
            ->and($array['appli'])->toBe(1)
            ->and($array['comment'])->toBe('Test subscription');
    });

    it('can create revoke parameters', function () {
        $params = NotificationParams::revoke(
            'https://example.com/webhook',
            NotificationApplication::WEIGHT
        );

        $array = $params->toArray();

        expect($array['action'])->toBe('revoke')
            ->and($array['callbackurl'])->toBe('https://example.com/webhook')
            ->and($array['appli'])->toBe(1)
            ->and($array)->not->toHaveKey('comment');
    });

    it('can create get parameters', function () {
        $params = NotificationParams::get(NotificationApplication::WEIGHT);

        $array = $params->toArray();

        expect($array['action'])->toBe('get')
            ->and($array['appli'])->toBe(1);
    });

    it('can create list parameters', function () {
        $params = NotificationParams::list();

        $array = $params->toArray();

        expect($array['action'])->toBe('list')
            ->and($array)->not->toHaveKey('appli');
    });
});
