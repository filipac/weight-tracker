<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\WeightEntry;
use App\Models\WeightGoal;
use Carbon\Carbon;

class AchievementService
{
    public function checkAndAwardAchievements()
    {
        $this->checkStreakAchievements();
        $this->checkMilestoneAchievements();
        $this->checkGoalAchievements();
    }

    public function getCurrentStreak()
    {
        $uniqueDates = WeightEntry::select('date')
            ->distinct()
            ->orderBy('date', 'desc')
            ->pluck('date');

        if ($uniqueDates->isEmpty()) {
            return 0;
        }

        $streak = 0;
        $currentDate = Carbon::today();

        foreach ($uniqueDates as $entryDate) {
            if ($entryDate->equalTo($currentDate) || $entryDate->equalTo($currentDate->subDay())) {
                $streak++;
                $currentDate = $entryDate->copy()->subDay();
            } else {
                break;
            }
        }

        return $streak;
    }

    private function checkStreakAchievements()
    {
        $currentStreak = $this->getCurrentStreak();
        $milestones = [7, 14, 30, 60, 100, 365];

        foreach ($milestones as $milestone) {
            if ($currentStreak >= $milestone) {
                $existingAchievement = Achievement::byType('streak')
                    ->where('value', $milestone)
                    ->exists();

                if (! $existingAchievement) {
                    Achievement::createStreakAchievement($milestone);
                }
            }
        }
    }

    private function checkMilestoneAchievements()
    {
        $firstEntry = WeightEntry::orderBy('date')->first();
        $latestEntry = WeightEntry::orderBy('date', 'desc')->first();

        if (! $firstEntry || ! $latestEntry) {
            return;
        }

        $weightLost = $firstEntry->weight_kg - $latestEntry->weight_kg;

        if ($weightLost <= 0) {
            return; // No weight lost
        }

        $milestones = [1, 5, 10, 15, 20, 25, 30];

        foreach ($milestones as $milestone) {
            if ($weightLost >= $milestone) {
                $existingAchievement = Achievement::byType('milestone')
                    ->where('value', $milestone)
                    ->exists();

                if (! $existingAchievement) {
                    Achievement::createMilestoneAchievement($milestone, $firstEntry->weight_kg);
                }
            }
        }
    }

    private function checkGoalAchievements()
    {
        $activeGoals = WeightGoal::active()->get();

        foreach ($activeGoals as $goal) {
            if ($goal->isAchieved()) {
                // Mark goal as achieved
                $goal->update(['status' => 'achieved']);

                // Create achievement if not already exists
                $existingAchievement = Achievement::byType('goal_achieved')
                    ->whereJsonContains('criteria->target_weight', $goal->target_weight)
                    ->whereJsonContains('criteria->goal_type', $goal->goal_type)
                    ->exists();

                if (! $existingAchievement) {
                    Achievement::createGoalAchievement($goal->target_weight, $goal->goal_type);
                }
            }
        }
    }

    public function getMotivationalMessage()
    {
        $currentStreak = $this->getCurrentStreak();
        $latestAchievement = Achievement::orderBy('earned_date', 'desc')->first();

        if ($latestAchievement && $latestAchievement->earned_date->isToday()) {
            return "🎉 Congratulations on earning '{$latestAchievement->title}'!";
        }

        if ($currentStreak > 0) {
            return "🔥 You're on a {$currentStreak} day logging streak! Keep it up!";
        }

        return '💪 Ready to start your weight tracking journey?';
    }
}
