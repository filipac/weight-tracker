<?php

declare(strict_types=1);

namespace Filipac\Withings\Enums;

enum SleepField: string
{
    case BREATHING_DISTURBANCES_INTENSITY = 'breathing_disturbances_intensity';
    case DEEP_SLEEP_DURATION = 'deepsleepduration';
    case DURATION_TO_SLEEP = 'durationtosleep';
    case DURATION_TO_WAKEUP = 'durationtowakeup';
    case HR_AVERAGE = 'hr_average';
    case HR_MAX = 'hr_max';
    case HR_MIN = 'hr_min';
    case LIGHT_SLEEP_DURATION = 'lightsleepduration';
    case REM_SLEEP_DURATION = 'remsleepduration';
    case RR_AVERAGE = 'rr_average';
    case RR_MAX = 'rr_max';
    case RR_MIN = 'rr_min';
    case SLEEP_SCORE = 'sleep_score';
    case SNORING = 'snoring';
    case SNORING_EPISODE_COUNT = 'snoringepisodecount';
    case WAKE_UP_COUNT = 'wakeupcount';
    case WAKE_UP_DURATION = 'wakeupduration';

    /**
     * Get human-readable name
     */
    public function getName(): string
    {
        return match ($this) {
            self::BREATHING_DISTURBANCES_INTENSITY => 'Breathing disturbances intensity',
            self::DEEP_SLEEP_DURATION => 'Deep sleep duration',
            self::DURATION_TO_SLEEP => 'Duration to fall asleep',
            self::DURATION_TO_WAKEUP => 'Duration to wake up',
            self::HR_AVERAGE => 'Average heart rate',
            self::HR_MAX => 'Maximum heart rate',
            self::HR_MIN => 'Minimum heart rate',
            self::LIGHT_SLEEP_DURATION => 'Light sleep duration',
            self::REM_SLEEP_DURATION => 'REM sleep duration',
            self::RR_AVERAGE => 'Average respiratory rate',
            self::RR_MAX => 'Maximum respiratory rate',
            self::RR_MIN => 'Minimum respiratory rate',
            self::SLEEP_SCORE => 'Sleep score',
            self::SNORING => 'Snoring',
            self::SNORING_EPISODE_COUNT => 'Snoring episode count',
            self::WAKE_UP_COUNT => 'Wake up count',
            self::WAKE_UP_DURATION => 'Wake up duration',
        };
    }

    /**
     * Get measurement unit
     */
    public function getUnit(): string
    {
        return match ($this) {
            self::BREATHING_DISTURBANCES_INTENSITY => 'intensity',
            self::DEEP_SLEEP_DURATION, self::DURATION_TO_SLEEP,
            self::DURATION_TO_WAKEUP, self::LIGHT_SLEEP_DURATION,
            self::REM_SLEEP_DURATION, self::WAKE_UP_DURATION => 'seconds',
            self::HR_AVERAGE, self::HR_MAX, self::HR_MIN => 'bpm',
            self::RR_AVERAGE, self::RR_MAX, self::RR_MIN => 'breaths/min',
            self::SLEEP_SCORE => 'score',
            self::SNORING => 'percentage',
            self::SNORING_EPISODE_COUNT, self::WAKE_UP_COUNT => 'count',
        };
    }
}
