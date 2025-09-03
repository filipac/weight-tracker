<?php

namespace App\Actions;

use App\Models\Achievement;
use App\Models\WeightEntry;
use App\Models\WeightGoal;
use Carbon\Carbon;

class GenerateWeightListAction
{
    public function execute(bool $reverse = false, bool $includeIds = false)
    {
        $entries = WeightEntry::orderBy('date')
            ->orderBy('id')
            ->get();

        if ($entries->isEmpty()) {
            return [];
        }

        $startingWeight = $entries->first()->weight_kg;
        $groupedEntries = $entries->groupBy('date');
        $result = [];
        $previousMonth = null;

        foreach ($groupedEntries as $date => $dayEntries) {
            $currentDate = Carbon::parse($date);
            $formattedDate = $currentDate->format('j F');
            $primaryEntry = $dayEntries->first();
            $difference = round($startingWeight - $primaryEntry->weight_kg, 2);

            // Check if this is the start of a new month and add monthly summary
            if ($previousMonth !== null && $currentDate->day === 1) {
                $monthlyWeightLoss = $this->calculateMonthlyWeightLoss($entries, $previousMonth);
                if ($monthlyWeightLoss > 0) {
                    $summaryLine = number_format($monthlyWeightLoss, 2).' kg jos in '.$previousMonth->format('F');
                    if ($includeIds) {
                        $result[] = ['text' => $summaryLine, 'id' => null, 'type' => 'summary'];
                    } else {
                        $result[] = $summaryLine;
                    }
                }
            }

            $line = $formattedDate.' - '.number_format($primaryEntry->weight_kg, 2);

            if ($difference != 0) {
                $line .= ' = '.number_format($difference, 2);
            }

            if ($dayEntries->count() > 1) {
                $additionalWeights = $dayEntries->skip(1)->pluck('weight_kg')->map(function ($weight) {
                    return number_format($weight, 2);
                })->toArray();
                $line .= ' '.implode(' ', $additionalWeights);
            }

            if ($includeIds) {
                $result[] = ['text' => $line, 'id' => $primaryEntry->id, 'type' => 'entry', 'date' => $primaryEntry->date, 'weight_kg' => $primaryEntry->weight_kg];
            } else {
                $result[] = $line;
            }

            $previousMonth = $currentDate;
        }

        // Add goal progress summary if not including IDs (for Notes.app)
        if (! $includeIds && ! $entries->isEmpty()) {
            $goalSummary = $this->generateGoalSummary();
            if (! empty($goalSummary)) {
                $result = array_merge($goalSummary, [''], $result);
            }

            $achievementSummary = $this->generateRecentAchievements();
            if (! empty($achievementSummary)) {
                $result = array_merge($achievementSummary, [''], $result);
            }
        }

        return $reverse ? array_reverse($result) : $result;
    }

    private function calculateMonthlyWeightLoss($entries, Carbon $month)
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        // Get first weight of the month
        $firstWeightOfMonth = $entries->filter(function ($entry) use ($monthStart, $monthEnd) {
            $entryDate = Carbon::parse($entry->date);

            return $entryDate->between($monthStart, $monthEnd);
        })->first();

        // Get last weight of the month
        $lastWeightOfMonth = $entries->filter(function ($entry) use ($monthStart, $monthEnd) {
            $entryDate = Carbon::parse($entry->date);

            return $entryDate->between($monthStart, $monthEnd);
        })->last();

        if (! $firstWeightOfMonth || ! $lastWeightOfMonth) {
            return 0;
        }

        return round($firstWeightOfMonth->weight_kg - $lastWeightOfMonth->weight_kg, 2);
    }

    private function generateGoalSummary()
    {
        $activeGoals = WeightGoal::active()->get();

        if ($activeGoals->isEmpty()) {
            return [];
        }

        $summary = ['🎯 GOALS:'];

        foreach ($activeGoals as $goal) {
            $progress = round($goal->getCurrentProgress(), 1);
            $status = $goal->isAchieved() ? '✅' : ($progress >= 75 ? '🔥' : '⭐');

            $goalLine = "{$status} {$goal->target_weight}kg";

            if ($goal->target_date) {
                $daysLeft = $goal->getDaysToTarget();
                if ($daysLeft !== null) {
                    if ($daysLeft > 0) {
                        $goalLine .= " ({$daysLeft} days left)";
                    } elseif ($daysLeft < 0) {
                        $goalLine .= ' (overdue)';
                    } else {
                        $goalLine .= ' (today!)';
                    }
                }
            }

            $goalLine .= " - {$progress}%";

            if ($goal->description) {
                $goalLine .= " ({$goal->description})";
            }

            $summary[] = $goalLine;
        }

        return $summary;
    }

    private function generateRecentAchievements()
    {
        $recentAchievements = Achievement::recent(7)->orderBy('earned_date', 'desc')->limit(3)->get();

        if ($recentAchievements->isEmpty()) {
            return [];
        }

        $summary = ['🏆 RECENT ACHIEVEMENTS:'];

        foreach ($recentAchievements as $achievement) {
            $icon = match ($achievement->type) {
                'streak' => '🔥',
                'milestone' => '🏅',
                'goal_achieved' => '🎯',
                default => '⭐'
            };

            $summary[] = "{$icon} {$achievement->title}";
        }

        return $summary;
    }
}
