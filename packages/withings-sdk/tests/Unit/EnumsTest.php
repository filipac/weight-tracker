<?php

use Filipac\Withings\Enums\ActivityField;
use Filipac\Withings\Enums\ApiAction;
use Filipac\Withings\Enums\MeasurementCategory;
use Filipac\Withings\Enums\MeasurementType;
use Filipac\Withings\Enums\NotificationApplication;

describe('MeasurementType', function () {
    it('has correct values for measurement types', function () {
        expect(MeasurementType::WEIGHT->value)->toBe(1)
            ->and(MeasurementType::HEIGHT->value)->toBe(4)
            ->and(MeasurementType::FAT_FREE_MASS->value)->toBe(5)
            ->and(MeasurementType::FAT_RATIO->value)->toBe(6)
            ->and(MeasurementType::FAT_MASS_WEIGHT->value)->toBe(8);
    });

    it('can get measurement type from value', function () {
        expect(MeasurementType::from(1))->toBe(MeasurementType::WEIGHT)
            ->and(MeasurementType::from(4))->toBe(MeasurementType::HEIGHT)
            ->and(MeasurementType::from(5))->toBe(MeasurementType::FAT_FREE_MASS);
    });

    it('can get all measurement type cases', function () {
        $cases = MeasurementType::cases();

        expect($cases)->toBeArray()
            ->and(count($cases))->toBeGreaterThan(5)
            ->and($cases)->toContain(MeasurementType::WEIGHT)
            ->and($cases)->toContain(MeasurementType::HEIGHT);
    });

    it('can get all measurement types as array', function () {
        $types = MeasurementType::getAllTypes();

        expect($types)->toBeArray()
            ->and($types)->toContain(MeasurementType::WEIGHT) // Weight enum
            ->and($types)->toContain(MeasurementType::HEIGHT); // Height enum
    });
});

describe('MeasurementCategory', function () {
    it('has correct values for measurement categories', function () {
        expect(MeasurementCategory::REAL->value)->toBe(1)
            ->and(MeasurementCategory::USER_OBJECTIVE->value)->toBe(2)
            ->and(MeasurementCategory::MANUAL->value)->toBe(4)
            ->and(MeasurementCategory::MEASURE_AUTO->value)->toBe(5);
    });

    it('can get category names', function () {
        expect(MeasurementCategory::REAL->getName())->toBe('Real measurement')
            ->and(MeasurementCategory::USER_OBJECTIVE->getName())->toBe('User objective')
            ->and(MeasurementCategory::MANUAL->getName())->toBe('Manual entry')
            ->and(MeasurementCategory::MEASURE_AUTO->getName())->toBe('Auto measurement');
    });

    it('can get category descriptions', function () {
        expect(MeasurementCategory::REAL->getDescription())->toBe('Real measurement taken by device')
            ->and(MeasurementCategory::USER_OBJECTIVE->getDescription())->toBe('User-defined objective or goal')
            ->and(MeasurementCategory::MANUAL->getDescription())->toBe('Manually entered measurement')
            ->and(MeasurementCategory::MEASURE_AUTO->getDescription())->toBe('Automatically measured by device');
    });
});

describe('NotificationApplication', function () {
    it('has correct values for notification applications', function () {
        expect(NotificationApplication::WEIGHT->value)->toBe(1)
            ->and(NotificationApplication::ACTIVITY->value)->toBe(4)
            ->and(NotificationApplication::SLEEP->value)->toBe(16)
            ->and(NotificationApplication::BED_IN_OUT->value)->toBe(44);
    });

    it('can get application names', function () {
        expect(NotificationApplication::WEIGHT->getName())->toBe('Weight measurements')
            ->and(NotificationApplication::ACTIVITY->getName())->toBe('Activity data')
            ->and(NotificationApplication::SLEEP->getName())->toBe('Sleep data')
            ->and(NotificationApplication::BED_IN_OUT->getName())->toBe('Bed in/out events');
    });
});

describe('ActivityField', function () {
    it('has correct values for activity fields', function () {
        expect(ActivityField::STEPS->value)->toBe('steps')
            ->and(ActivityField::DISTANCE->value)->toBe('distance')
            ->and(ActivityField::ELEVATION->value)->toBe('elevation')
            ->and(ActivityField::SOFT->value)->toBe('soft')
            ->and(ActivityField::MODERATE->value)->toBe('moderate')
            ->and(ActivityField::INTENSE->value)->toBe('intense');
    });

    it('can get all activity field cases', function () {
        $cases = ActivityField::cases();

        expect($cases)->toBeArray()
            ->and($cases)->toContain(ActivityField::STEPS)
            ->and($cases)->toContain(ActivityField::DISTANCE)
            ->and($cases)->toContain(ActivityField::CALORIES);
    });
});

describe('ApiAction', function () {
    it('has correct values for API actions', function () {
        expect(ApiAction::GET_MEASURES->value)->toBe('getmeas')
            ->and(ApiAction::GET_ACTIVITY->value)->toBe('getactivity')
            ->and(ApiAction::GET_INTRADAY_ACTIVITY->value)->toBe('getintradayactivity')
            ->and(ApiAction::GET_WORKOUTS->value)->toBe('getworkouts');
    });

    it('can get all API action cases', function () {
        $cases = ApiAction::cases();

        expect($cases)->toBeArray()
            ->and($cases)->toContain(ApiAction::GET_MEASURES)
            ->and($cases)->toContain(ApiAction::GET_ACTIVITY);
    });
});
