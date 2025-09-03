<?php

namespace Filipac\Withings\Enums;

enum IntradayActivityField: string
{
    case STEPS = 'steps';
    case ELEVATION = 'elevation';
    case CALORIES = 'calories';
    case DISTANCE = 'distance';
    case STROKE = 'stroke';
    case POOL_LAP = 'pool_lap';
    case DURATION = 'duration';
    case HEART_RATE = 'heart_rate';

    /**
     * Get human-readable name
     */
    public function getName(): string
    {
        return match($this) {
            self::STEPS => 'Steps',
            self::ELEVATION => 'Elevation',
            self::CALORIES => 'Calories',
            self::DISTANCE => 'Distance',
            self::STROKE => 'Swimming strokes',
            self::POOL_LAP => 'Pool laps',
            self::DURATION => 'Activity duration',
            self::HEART_RATE => 'Heart rate',
        };
    }

    /**
     * Get measurement unit
     */
    public function getUnit(): string
    {
        return match($this) {
            self::STEPS => 'steps',
            self::ELEVATION => 'm',
            self::CALORIES => 'kcal',
            self::DISTANCE => 'm',
            self::STROKE => 'strokes',
            self::POOL_LAP => 'laps',
            self::DURATION => 'seconds',
            self::HEART_RATE => 'bpm',
        };
    }
}