<?php

namespace Filipac\Withings\Enums;

enum MeasurementCategory: int
{
    case REAL = 1;
    case USER_OBJECTIVE = 2;
    case MANUAL = 4;
    case MEASURE_AUTO = 5;

    public function getName(): string
    {
        return match ($this) {
            self::REAL => 'Real measurement',
            self::USER_OBJECTIVE => 'User objective',
            self::MANUAL => 'Manual entry',
            self::MEASURE_AUTO => 'Auto measurement',
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::REAL => 'Real measurement taken by device',
            self::USER_OBJECTIVE => 'User-defined objective or goal',
            self::MANUAL => 'Manually entered measurement',
            self::MEASURE_AUTO => 'Automatically measured by device',
        };
    }
}