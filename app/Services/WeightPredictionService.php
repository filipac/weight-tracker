<?php

namespace App\Services;

use App\Models\WeightEntry;
use App\Models\WeightGoal;
use Carbon\Carbon;

class WeightPredictionService
{
    /**
     * Calculate recent-trend weight predictions.
     *
     * Forecasts use daily averaged entries and the latest realistic trend instead
     * of the full historical journey, which can make short-term predictions too
     * optimistic after a large long-term weight loss.
     */
    public function calculatePredictions(): array
    {
        $entries = WeightEntry::orderBy('date')->get();

        $dailyEntries = $this->averageEntriesByDate($entries);

        if ($dailyEntries->count() < 2) {
            return [
                'hasEnoughData' => false,
                'nextMonthPrediction' => null,
                'goalDate' => null,
                'goalDate90' => null,
                'goalPredictions' => [],
                'dailyWeightLoss' => null,
                'allTimeDailyWeightLoss' => null,
                'confidence' => 0,
            ];
        }

        $firstEntry = $dailyEntries->first();

        // Get the latest entry to use as the starting point for predictions
        $latestEntry = $dailyEntries->last();
        $latestEntryDate = $latestEntry['date'];
        $latestEntryWeight = $latestEntry['weight'];
        $allTimeDays = $firstEntry['date']->diffInDays($latestEntryDate);
        $allTimeSlope = $allTimeDays > 0
            ? ($latestEntryWeight - $firstEntry['weight']) / $allTimeDays
            : 0;

        $forecastEntries = $this->getForecastEntries($dailyEntries, $latestEntryDate);
        $forecastFirstDate = $forecastEntries->first()['date'];
        $forecastDays = [];
        $forecastWeights = [];

        foreach ($forecastEntries as $entry) {
            $forecastDays[] = $forecastFirstDate->diffInDays($entry['date']);
            $forecastWeights[] = $entry['weight'];
        }

        $forecastRegression = $this->linearRegression($forecastDays, $forecastWeights);
        $forecastSlope = $forecastRegression['slope'];
        $confidence = $this->calculateRSquared($forecastDays, $forecastWeights, $forecastRegression);

        // Predict weight for first day of next month
        $nextMonthDate = Carbon::now()->addMonth()->startOfMonth();
        $daysFromLatestToNextMonth = $latestEntryDate->diffInDays($nextMonthDate);

        // Use recent forecast slope for prediction
        $nextMonthWeight = $latestEntryWeight + ($forecastSlope * $daysFromLatestToNextMonth);

        // Calculate BMI for next month prediction (height hardcoded to 175cm = 1.75m)
        $heightInMeters = 1.75;
        $nextMonthBMI = round($nextMonthWeight / ($heightInMeters * $heightInMeters), 1);

        // Calculate prediction dates for active goals
        $activeGoals = WeightGoal::active()->get();
        $goalPredictions = [];

        $currentWeight = $latestEntryWeight;

        foreach ($activeGoals as $goal) {
            $goalDate = null;
            $targetWeight = $goal->target_weight;

            // Check if prediction is relevant based on goal type and current trend
            $shouldPredict = false;

            switch ($goal->goal_type) {
                case 'lose':
                    $shouldPredict = $forecastSlope < 0 && $currentWeight > $targetWeight;
                    break;
                case 'gain':
                    $shouldPredict = $forecastSlope > 0 && $currentWeight < $targetWeight;
                    break;
                case 'maintain':
                    // For maintenance, show if we're close (within 5kg)
                    $shouldPredict = abs($currentWeight - $targetWeight) <= 5;
                    break;
            }

            if ($shouldPredict && $forecastSlope != 0) {
                // Calculate days needed to reach goal from current weight using forecast slope
                $weightDifference = $targetWeight - $latestEntryWeight;
                $daysToGoal = $weightDifference / $forecastSlope;
                if ($daysToGoal > 0) {
                    $goalDate = $latestEntryDate->copy()->addDays(round($daysToGoal));
                }
            }

            // Calculate BMI for goal weight
            $goalBMI = round($targetWeight / ($heightInMeters * $heightInMeters), 1);

            $goalPredictions[] = [
                'id' => $goal->id,
                'target_weight' => $targetWeight,
                'goal_type' => $goal->goal_type,
                'prediction_date' => $goalDate ? $goalDate->format('j F Y') : null,
                'prediction_date_raw' => $goalDate ? $goalDate->format('Y-m-d') : null,
                'description' => $goal->description,
                'goal_bmi' => $goalBMI,
            ];
        }

        // Keep legacy 100kg and 90kg for backwards compatibility if no custom goals
        $goalDate = null;
        $goalDate90 = null;

        if ($activeGoals->isEmpty()) {
            if ($forecastSlope < 0 && $latestEntryWeight > 100) {
                $weightDifference = 100 - $latestEntryWeight;
                $daysToGoal = $weightDifference / $forecastSlope;
                if ($daysToGoal > 0) {
                    $goalDate = $latestEntryDate->copy()->addDays(round($daysToGoal));
                }
            }

            if ($forecastSlope < 0 && $latestEntryWeight > 90) {
                $weightDifference90 = 90 - $latestEntryWeight;
                $daysToGoal90 = $weightDifference90 / $forecastSlope;
                if ($daysToGoal90 > 0) {
                    $goalDate90 = $latestEntryDate->copy()->addDays(round($daysToGoal90));
                }
            }
        }

        // Calculate when user will reach healthy BMI range (BMI 25 for 175cm height)
        $healthyBMIDate = null;
        $healthyBMIWeight = 25 * ($heightInMeters * $heightInMeters); // Max healthy weight for height
        $currentBMI = $latestEntryWeight / ($heightInMeters * $heightInMeters);

        if ($forecastSlope < 0 && $currentBMI > 25) {
            // User is losing weight and currently above healthy range
            $weightDifferenceToHealthy = $healthyBMIWeight - $latestEntryWeight;
            $daysToHealthyBMI = $weightDifferenceToHealthy / $forecastSlope;
            if ($daysToHealthyBMI > 0) {
                $healthyBMIDate = $latestEntryDate->copy()->addDays(round($daysToHealthyBMI));
            }
        }

        return [
            'hasEnoughData' => true,
            'nextMonthPrediction' => round($nextMonthWeight, 2),
            'nextMonthBMI' => $nextMonthBMI,
            'nextMonthDate' => $nextMonthDate->format('j F Y'),
            'goalDate' => $goalDate ? $goalDate->format('j F Y') : null,
            'goalDate90' => $goalDate90 ? $goalDate90->format('j F Y') : null,
            'goalPredictions' => $goalPredictions,
            'healthyBMIDate' => $healthyBMIDate ? $healthyBMIDate->format('j F Y') : null,
            'healthyBMIWeight' => round($healthyBMIWeight, 1),
            'dailyWeightLoss' => round(abs($forecastSlope), 3),
            'dailyWeightChange' => round($forecastSlope, 4), // Signed value for frontend calculations
            'allTimeDailyWeightLoss' => round(abs($allTimeSlope), 3),
            'allTimeDailyWeightChange' => round($allTimeSlope, 4),
            'latestEntryDate' => $latestEntryDate->format('Y-m-d'),
            'latestEntryWeight' => round($latestEntryWeight, 2),
            'confidence' => round($confidence * 100, 1),
            'trend' => $forecastSlope < 0 ? 'losing' : 'gaining',
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

        $denominator = $n * $sumXX - $sumX * $sumX;

        if (abs($denominator) < 0.0001) {
            return [
                'slope' => 0,
                'intercept' => $n > 0 ? $sumY / $n : 0,
            ];
        }

        $slope = ($n * $sumXY - $sumX * $sumY) / $denominator;
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
     * Average multiple entries from the same day so a date with duplicate
     * weigh-ins does not get extra influence over the forecast.
     */
    private function averageEntriesByDate($entries)
    {
        return $entries
            ->groupBy(fn ($entry) => $entry->date->format('Y-m-d'))
            ->map(function ($entriesForDate, $date) {
                return [
                    'date' => Carbon::parse($date),
                    'weight' => $entriesForDate->avg(fn ($entry) => (float) $entry->weight_kg),
                ];
            })
            ->sortBy('date')
            ->values();
    }

    /**
     * Get the forecast window.
     *
     * Prefer the last 30 calendar days when it has enough distinct dates,
     * otherwise fall back to the last 10 distinct dates.
     */
    private function getForecastEntries($dailyEntries, Carbon $latestDate)
    {
        $thirtyDaysAgo = $latestDate->copy()->subDays(30);
        $recentByDate = $dailyEntries->filter(function ($entry) use ($thirtyDaysAgo) {
            return $entry['date'] >= $thirtyDaysAgo;
        })->values();

        if ($recentByDate->count() >= 5) {
            return $recentByDate;
        }

        $last10Entries = $dailyEntries->slice(-10)->values();

        return $last10Entries->count() >= 2
            ? $last10Entries
            : $dailyEntries;
    }
}
