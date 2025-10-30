<?php

namespace App\Services;

use App\Models\WeightEntry;
use App\Models\WeightGoal;
use Carbon\Carbon;

class WeightPredictionService
{
    /**
     * Calculate linear regression for weight trend prediction
     * Uses multiple strategies for better accuracy:
     * 1. Recent trend (last 7-30 days) - most relevant for short-term predictions
     * 2. Weighted regression (favors recent data) - balances history with recent changes
     * 3. Overall trend - provides stability
     * Predictions combine these strategies with appropriate weights
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

        // Get the latest entry to use as the starting point for predictions
        $latestEntry = $entries->last();
        $latestEntryDate = $latestEntry->date;
        $latestEntryWeight = (float) $latestEntry->weight_kg;

        // Convert dates to days since first entry for regression
        $firstDate = $entries->first()->date;
        $weights = [];
        $days = [];

        foreach ($entries as $entry) {
            $daysSinceStart = $firstDate->diffInDays($entry->date);
            $days[] = $daysSinceStart;
            $weights[] = (float) $entry->weight_kg;
        }

        // Strategy 1: Overall linear regression (all data)
        $overallRegression = $this->linearRegression($days, $weights);
        $overallSlope = $overallRegression['slope'];

        // Strategy 2: Recent trend analysis (last 7-30 days or last 10 entries)
        $recentEntries = $this->getRecentEntries($entries, $latestEntryDate);
        $recentSlope = $overallSlope; // Default to overall if not enough recent data

        if ($recentEntries->count() >= 2) {
            $recentFirstDate = $recentEntries->first()->date;
            $recentDays = [];
            $recentWeights = [];

            foreach ($recentEntries as $entry) {
                $recentDays[] = $recentFirstDate->diffInDays($entry->date);
                $recentWeights[] = (float) $entry->weight_kg;
            }

            $recentRegression = $this->linearRegression($recentDays, $recentWeights);
            $recentSlope = $recentRegression['slope'];
        }

        // Strategy 3: Weighted regression (favors recent data exponentially)
        $entryWeights = [];
        foreach ($entries as $index => $entry) {
            // Exponential weight: recent entries get much higher weight
            // Weight increases exponentially for newer entries
            $normalizedIndex = ($index + 1) / $entries->count();
            $entryWeights[] = pow(2, $normalizedIndex);
        }
        $weightedRegression = $this->weightedLinearRegression($days, $weights, $entryWeights);
        $weightedSlope = $weightedRegression['slope'];

        // Combine strategies with adaptive weights
        // If we have good recent data, favor recent trend more
        // Otherwise, use weighted regression as primary
        $recentWeight = $recentEntries->count() >= 5 ? 0.5 : 0.3;
        $weightedWeight = 0.4;
        $overallWeight = 1 - $recentWeight - $weightedWeight;

        $combinedSlope = ($recentSlope * $recentWeight)
                       + ($weightedSlope * $weightedWeight)
                       + ($overallSlope * $overallWeight);

        // Calculate confidence based on recent data quality
        $confidenceDays = $recentEntries->count() >= 5 ? $recentEntries : $entries;
        $confidenceFirstDate = $confidenceDays->first()->date;
        $confidenceDaysArray = [];
        $confidenceWeightsArray = [];

        foreach ($confidenceDays as $entry) {
            $confidenceDaysArray[] = $confidenceFirstDate->diffInDays($entry->date);
            $confidenceWeightsArray[] = (float) $entry->weight_kg;
        }

        $confidenceRegression = $this->linearRegression($confidenceDaysArray, $confidenceWeightsArray);
        $confidence = $this->calculateRSquared($confidenceDaysArray, $confidenceWeightsArray, $confidenceRegression);

        // Predict weight for first day of next month
        $nextMonthDate = Carbon::now()->addMonth()->startOfMonth();
        $daysFromLatestToNextMonth = $latestEntryDate->diffInDays($nextMonthDate);

        // Use combined slope for prediction
        $nextMonthWeight = $latestEntryWeight + ($combinedSlope * $daysFromLatestToNextMonth);

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
                    $shouldPredict = $combinedSlope < 0 && $currentWeight > $targetWeight;
                    break;
                case 'gain':
                    $shouldPredict = $combinedSlope > 0 && $currentWeight < $targetWeight;
                    break;
                case 'maintain':
                    // For maintenance, show if we're close (within 5kg)
                    $shouldPredict = abs($currentWeight - $targetWeight) <= 5;
                    break;
            }

            if ($shouldPredict && $combinedSlope != 0) {
                // Calculate days needed to reach goal from current weight using combined slope
                $weightDifference = $targetWeight - $latestEntryWeight;
                $daysToGoal = $weightDifference / $combinedSlope;
                if ($daysToGoal > 0) {
                    $goalDate = $latestEntryDate->copy()->addDays(round($daysToGoal));
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
            if ($combinedSlope < 0 && $latestEntryWeight > 100) {
                $weightDifference = 100 - $latestEntryWeight;
                $daysToGoal = $weightDifference / $combinedSlope;
                if ($daysToGoal > 0) {
                    $goalDate = $latestEntryDate->copy()->addDays(round($daysToGoal));
                }
            }

            if ($combinedSlope < 0 && $latestEntryWeight > 90) {
                $weightDifference90 = 90 - $latestEntryWeight;
                $daysToGoal90 = $weightDifference90 / $combinedSlope;
                if ($daysToGoal90 > 0) {
                    $goalDate90 = $latestEntryDate->copy()->addDays(round($daysToGoal90));
                }
            }
        }

        return [
            'hasEnoughData' => true,
            'nextMonthPrediction' => round($nextMonthWeight, 2),
            'nextMonthDate' => $nextMonthDate->format('j F Y'),
            'goalDate' => $goalDate ? $goalDate->format('j F Y') : null,
            'goalDate90' => $goalDate90 ? $goalDate90->format('j F Y') : null,
            'goalPredictions' => $goalPredictions,
            'dailyWeightLoss' => round(abs($combinedSlope), 3),
            'confidence' => round($confidence * 100, 1),
            'trend' => $combinedSlope < 0 ? 'losing' : 'gaining',
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

    /**
     * Get recent entries for trend analysis
     * Uses last 30 days OR last 10 entries, whichever gives more data points
     * Recent data is more relevant for short-term predictions
     */
    private function getRecentEntries($entries, Carbon $latestDate)
    {
        // Get entries from last 30 days
        $thirtyDaysAgo = $latestDate->copy()->subDays(30);
        $recentByDate = $entries->filter(function ($entry) use ($thirtyDaysAgo) {
            return $entry->date >= $thirtyDaysAgo;
        });

        // Get last 10 entries
        $last10Entries = $entries->slice(-10);

        // Return whichever has more entries (more data = better trend)
        return $recentByDate->count() >= $last10Entries->count()
            ? $recentByDate
            : $last10Entries;
    }

    /**
     * Calculate weighted linear regression slope and intercept
     * Uses exponential weighting to favor recent data
     * Recent entries get exponentially more weight in the calculation
     */
    private function weightedLinearRegression(array $x, array $y, array $w): array
    {
        $n = count($x);

        if ($n === 0 || count($y) !== $n || count($w) !== $n) {
            // Fallback to regular regression if weights don't match
            return $this->linearRegression($x, $y);
        }

        $sumW = array_sum($w);

        if ($sumW == 0) {
            return $this->linearRegression($x, $y);
        }

        // Calculate weighted sums
        $sumWX = 0;
        $sumWY = 0;
        $sumWXY = 0;
        $sumWXX = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumWX += $w[$i] * $x[$i];
            $sumWY += $w[$i] * $y[$i];
            $sumWXY += $w[$i] * $x[$i] * $y[$i];
            $sumWXX += $w[$i] * $x[$i] * $x[$i];
        }

        // Weighted linear regression formula
        $denominator = ($sumW * $sumWXX) - ($sumWX * $sumWX);

        if (abs($denominator) < 0.0001) {
            // Fallback to regular regression if weights cause numerical issues
            return $this->linearRegression($x, $y);
        }

        $slope = (($sumW * $sumWXY) - ($sumWX * $sumWY)) / $denominator;
        $intercept = ($sumWY - $slope * $sumWX) / $sumW;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
        ];
    }
}
