<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Achievement extends Model
{
    protected $fillable = [
        'type',
        'title',
        'description',
        'criteria',
        'earned_date',
        'value',
    ];

    protected $casts = [
        'criteria' => 'array',
        'earned_date' => 'date',
    ];

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRecent($query, $days = 30)
    {
        return $query->where('earned_date', '>=', now()->subDays($days));
    }

    public static function createStreakAchievement($days)
    {
        return self::create([
            'type' => 'streak',
            'title' => "{$days} Day Logging Streak",
            'description' => "Logged weight for {$days} consecutive days",
            'criteria' => ['days' => $days],
            'earned_date' => today(),
            'value' => $days,
        ]);
    }

    public static function createMilestoneAchievement($weightLost, $startWeight)
    {
        return self::create([
            'type' => 'milestone',
            'title' => "{$weightLost}kg Weight Loss",
            'description' => "Lost {$weightLost}kg from starting weight of {$startWeight}kg",
            'criteria' => ['weight_lost' => $weightLost],
            'earned_date' => today(),
            'value' => $weightLost,
        ]);
    }

    public static function createGoalAchievement($goalWeight, $goalType)
    {
        $action = $goalType === 'lose' ? 'Reached' : ($goalType === 'gain' ? 'Reached' : 'Maintained');

        return self::create([
            'type' => 'goal_achieved',
            'title' => "{$action} {$goalWeight}kg Goal",
            'description' => "Successfully {$action} target weight of {$goalWeight}kg",
            'criteria' => ['target_weight' => $goalWeight, 'goal_type' => $goalType],
            'earned_date' => today(),
            'value' => $goalWeight,
        ]);
    }
}
