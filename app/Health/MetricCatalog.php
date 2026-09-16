<?php

namespace App\Health;

class MetricCatalog
{
    public const SOURCES = ['withings' => 'Withings', 'oura' => 'Oura', 'apple_health' => 'Apple Health'];

    public const WORKOUT_TYPES = ['other' => 'Workout', 'indoor-run' => 'Indoor run', 'outdoor-run' => 'Outdoor run', 'running' => 'Running', 'indoor-walk' => 'Indoor walk', 'outdoor-walk' => 'Outdoor walk', 'walking' => 'Walking', 'strength' => 'Strength training', 'indoor-cycling' => 'Indoor cycling', 'cycling' => 'Cycling', 'swimming' => 'Swimming', 'hiking' => 'Hiking', 'yoga' => 'Yoga', 'pilates' => 'Pilates', 'rowing' => 'Rowing', 'elliptical' => 'Elliptical', 'hiit' => 'High-intensity intervals'];

    public const TOPICS = ['weight' => 'Weight', 'body-composition' => 'Body composition', 'activity' => 'Activity', 'heart' => 'Heart', 'sleep' => 'Sleep', 'recovery' => 'Recovery', 'vitals' => 'Vitals', 'mindfulness' => 'Mindfulness'];

    public static function all(): array
    {
        static $catalog;

        return $catalog ??= json_decode(file_get_contents(__DIR__.'/metrics.json'), true, flags: JSON_THROW_ON_ERROR);
    }
}
