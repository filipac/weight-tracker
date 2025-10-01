<?php

namespace App\Services;

use App\Models\WeightEntry;
use App\Models\WeightGoal;
use Carbon\Carbon;

class WeightPredictionService
{
    /**
     * Calculate linear regression for weight trend prediction
     */
    public function calculatePredictions(): array
    {
        $entries = WeightEntry::orderBy('date')->get();

        if ($entries->count() < 2) {
            return [
                'hasEnoughData' => false,
                'nextMonthPrediction' => null,
                'goalDate' => null,
                'goalDate90' => null,
                'goalPredictions' => [],
                'dailyWeightLoss' => null,
                'confidence' => 0,
            ];
        }

        // Convert dates to days since first entry for regression
        $firstDate = $entries->first()->date;
        $weights = [];
        $days = [];

        foreach ($entries as $entry) {
            $daysSinceStart = $firstDate->diffInDays($entry->date);
            $days[] = $daysSinceStart;
            $weights[] = (float) $entry->weight_kg;
        }

        // Calculate linear regression
        $regression = $this->linearRegression($days, $weights);

        // Calculate R-squared for confidence
        $confidence = $this->calculateRSquared($days, $weights, $regression);

        // Predict weight for first day of next month
        $nextMonthDate = Carbon::now()->addMonth()->startOfMonth();
        $daysToNextMonth = $firstDate->diffInDays($nextMonthDate);
        $nextMonthWeight = $regression['slope'] * $daysToNextMonth + $regression['intercept'];

        // Calculate prediction dates for active goals
        $activeGoals = WeightGoal::active()->get();
        $goalPredictions = [];

        $currentWeight = $entries->last()->weight_kg;

        foreach ($activeGoals as $goal) {
            $goalDate = null;
            $targetWeight = $goal->target_weight;

            // Check if prediction is relevant based on goal type and current trend
            $shouldPredict = false;

            switch ($goal->goal_type) {
                case 'lose':
                    $shouldPredict = $regression['slope'] < 0 && $currentWeight > $targetWeight;
                    break;
                case 'gain':
                    $shouldPredict = $regression['slope'] > 0 && $currentWeight < $targetWeight;
                    break;
                case 'maintain':
                    // For maintenance, show if we're close (within 5kg)
                    $shouldPredict = abs($currentWeight - $targetWeight) <= 5;
                    break;
            }

            if ($shouldPredict && $regression['slope'] != 0) {
                $daysToGoal = ($targetWeight - $regression['intercept']) / $regression['slope'];
                if ($daysToGoal > 0) {
                    $goalDate = $firstDate->copy()->addDays(round($daysToGoal));
                }
            }

            $goalPredictions[] = [
                'id' => $goal->id,
                'target_weight' => $targetWeight,
                'goal_type' => $goal->goal_type,
                'prediction_date' => $goalDate ? $goalDate->format('j F Y') : null,
                'description' => $goal->description,
            ];
        }

        // Keep legacy 100kg and 90kg for backwards compatibility if no custom goals
        $goalDate = null;
        $goalDate90 = null;

        if ($activeGoals->isEmpty()) {
            if ($regression['slope'] < 0 && $nextMonthWeight > 100) {
                $daysToGoal = (100 - $regression['intercept']) / $regression['slope'];
                $goalDate = $firstDate->copy()->addDays(round($daysToGoal));
            }

            if ($regression['slope'] < 0 && $nextMonthWeight > 90) {
                $daysToGoal90 = (90 - $regression['intercept']) / $regression['slope'];
                $goalDate90 = $firstDate->copy()->addDays(round($daysToGoal90));
            }
        }

        return [
            'hasEnoughData' => true,
            'nextMonthPrediction' => round($nextMonthWeight, 2),
            'nextMonthDate' => $nextMonthDate->format('j F Y'),
            'goalDate' => $goalDate ? $goalDate->format('j F Y') : null,
            'goalDate90' => $goalDate90 ? $goalDate90->format('j F Y') : null,
            'goalPredictions' => $goalPredictions,
            'dailyWeightLoss' => round(abs($regression['slope']), 3),
            'confidence' => round($confidence * 100, 1),
            'trend' => $regression['slope'] < 0 ? 'losing' : 'gaining',
            'entryCount' => $entries->count(),
        ];
    }

    /**
     * Calculate linear regression slope and intercept
     */
    private function linearRegression(array $x, array $y): array
    {
        $n = count($x);
        $sumX = array_sum($x);
        $sumY = array_sum($y);
        $sumXY = 0;
        $sumXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumXY += $x[$i] * $y[$i];
            $sumXX += $x[$i] * $x[$i];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumXX - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
        ];
    }

    /**
     * Calculate R-squared for regression confidence
     */
    private function calculateRSquared(array $x, array $y, array $regression): float
    {
        $yMean = array_sum($y) / count($y);
        $ssTotal = 0;
        $ssResidual = 0;

        for ($i = 0; $i < count($x); $i++) {
            $predicted = $regression['slope'] * $x[$i] + $regression['intercept'];
            $ssTotal += ($y[$i] - $yMean) ** 2;
            $ssResidual += ($y[$i] - $predicted) ** 2;
        }

        return $ssTotal > 0 ? 1 - ($ssResidual / $ssTotal) : 0;
    }
}
