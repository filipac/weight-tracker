<?php

namespace Filipac\Withings\Enums;

enum NotificationApplication: int
{
    case WEIGHT = 1;
    case ACTIVITY = 4;
    case SLEEP = 16;
    case BED_IN_OUT = 44;
    case USER_GOALS = 50;
    case USER_ACTIVITY_GOALS = 51;

    /**
     * Get human-readable name
     */
    public function getName(): string
    {
        return match($this) {
            self::WEIGHT => 'Weight measurements',
            self::ACTIVITY => 'Activity data',
            self::SLEEP => 'Sleep data',
            self::BED_IN_OUT => 'Bed in/out events',
            self::USER_GOALS => 'User goals',
            self::USER_ACTIVITY_GOALS => 'User activity goals',
        };
    }

    /**
     * Get description
     */
    public function getDescription(): string
    {
        return match($this) {
            self::WEIGHT => 'Notifications for weight and body composition measurements',
            self::ACTIVITY => 'Notifications for activity and workout data',
            self::SLEEP => 'Notifications for sleep tracking data',
            self::BED_IN_OUT => 'Notifications for bed in/out detection events',
            self::USER_GOALS => 'Notifications for user goal updates',
            self::USER_ACTIVITY_GOALS => 'Notifications for user activity goal updates',
        };
    }
}