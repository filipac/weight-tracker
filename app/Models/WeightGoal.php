<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class WeightGoal extends Model
{
    protected $fillable = [
        'target_weight',
        'target_date',
        'goal_type',
        'status',
        'description',
        'starting_weight',
        'created_date',
    ];

    protected $casts = [
        'target_weight' => 'decimal:2',
        'starting_weight' => 'decimal:2',
        'target_date' => 'date',
        'created_date' => 'date',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeAchieved($query)
    {
        return $query->where('status', 'achieved');
    }

    public function getCurrentProgress()
    {
        $latestWeight = WeightEntry::orderBy('date', 'desc')->first();

        if (! $latestWeight || ! $this->starting_weight) {
            return 0;
        }

        $totalDistance = $this->target_weight - $this->starting_weight;
        $currentDistance = $latestWeight->weight_kg - $this->starting_weight;

        // If no distance needed (already at target), return 100%
        if (abs($totalDistance) < 0.1) {
            return 100;
        }

        // Calculate progress based on goal type
        switch ($this->goal_type) {
            case 'lose':
                // For weight loss: progress = weight lost / total weight to lose
                $progress = (-$currentDistance) / (-$totalDistance) * 100;
                break;

            case 'gain':
                // For weight gain: progress = weight gained / total weight to gain
                $progress = $currentDistance / $totalDistance * 100;
                break;

            case 'maintain':
                // For maintenance: progress based on how close to target (within 1kg = 100%)
                $distanceFromTarget = abs($latestWeight->weight_kg - $this->target_weight);
                $progress = max(0, (1 - $distanceFromTarget) * 100);
                break;

            default:
                $progress = 0;
        }

        return min(100, max(0, $progress));
    }

    public function isAchieved()
    {
        $latestWeight = WeightEntry::orderBy('date', 'desc')->first();

        if (! $latestWeight) {
            return false;
        }

        switch ($this->goal_type) {
            case 'lose':
                return $latestWeight->weight_kg <= $this->target_weight;
            case 'gain':
                return $latestWeight->weight_kg >= $this->target_weight;
            case 'maintain':
                $tolerance = 1.0; // 1kg tolerance for maintenance

                return abs($latestWeight->weight_kg - $this->target_weight) <= $tolerance;
            default:
                return false;
        }
    }

    public function getDaysToTarget()
    {
        if (! $this->target_date) {
            return null;
        }

        return Carbon::now()->diffInDays($this->target_date, false);
    }
}
