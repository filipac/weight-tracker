<?php

namespace Filipac\Withings\Enums;

enum ActivityField: string
{
    case STEPS = 'steps';
    case DISTANCE = 'distance';
    case ELEVATION = 'elevation';
    case SOFT = 'soft';
    case MODERATE = 'moderate';
    case INTENSE = 'intense';
    case ACTIVE = 'active';
    case CALORIES = 'calories';
    case TOTAL_CALORIES = 'totalcalories';
    case HR_AVERAGE = 'hr_average';
    case HR_MIN = 'hr_min';
    case HR_MAX = 'hr_max';
    case HR_ZONE_0 = 'hr_zone_0';
    case HR_ZONE_1 = 'hr_zone_1';
    case HR_ZONE_2 = 'hr_zone_2';
    case HR_ZONE_3 = 'hr_zone_3';

    /**
     * Get human-readable name
     */
    public function getName(): string
    {
        return match($this) {
            self::STEPS => 'Steps',
            self::DISTANCE => 'Distance',
            self::ELEVATION => 'Elevation',
            self::SOFT => 'Soft activity duration',
            self::MODERATE => 'Moderate activity duration',
            self::INTENSE => 'Intense activity duration',
            self::ACTIVE => 'Active duration',
            self::CALORIES => 'Active calories',
            self::TOTAL_CALORIES => 'Total calories',
            self::HR_AVERAGE => 'Average heart rate',
            self::HR_MIN => 'Minimum heart rate',
            self::HR_MAX => 'Maximum heart rate',
            self::HR_ZONE_0 => 'Heart rate zone 0',
            self::HR_ZONE_1 => 'Heart rate zone 1',
            self::HR_ZONE_2 => 'Heart rate zone 2',
            self::HR_ZONE_3 => 'Heart rate zone 3',
        };
    }

    /**
     * Get measurement unit
     */
    public function getUnit(): string
    {
        return match($this) {
            self::STEPS => 'steps',
            self::DISTANCE => 'm',
            self::ELEVATION => 'm',
            self::SOFT, self::MODERATE, self::INTENSE, self::ACTIVE => 'seconds',
            self::CALORIES, self::TOTAL_CALORIES => 'kcal',
            self::HR_AVERAGE, self::HR_MIN, self::HR_MAX => 'bpm',
            self::HR_ZONE_0, self::HR_ZONE_1, self::HR_ZONE_2, self::HR_ZONE_3 => 'seconds',
        };
    }
}