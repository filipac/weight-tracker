<?php

namespace App\Console\Commands;

use App\Models\WeightEntry;
use Carbon\Carbon;
use Illuminate\Console\Command;

class WeightRateReportCommand extends Command
{
    protected $signature = 'weight:rate-report {--pdf : Generate a PDF report instead of text}';

    protected $description = 'Generate a shareable daily rate report with historical trends';

    private const WINDOW_DAYS = 30;

    public function handle()
    {
        $entries = WeightEntry::orderBy('date', 'asc')->get();

        if ($entries->count() < 2) {
            $this->warn('Not enough data to generate a rate report.');

            return;
        }

        $sorted = $entries->map(fn ($e) => [
            'date' => $e->date,
            'weight' => (float) $e->weight_kg,
        ])->values()->all();

        $firstDate = $sorted[0]['date'];

        // Build unique dates with at least WINDOW_DAYS of prior data
        $windowStart = $firstDate->copy()->addDays(self::WINDOW_DAYS);
        $dateIndex = [];
        $seen = [];

        foreach ($sorted as $entry) {
            $key = $entry['date']->format('Y-m-d');
            if ($entry['date']->gte($windowStart) && ! isset($seen[$key])) {
                $seen[$key] = true;
                $dateIndex[] = $entry['date'];
            }
        }

        if (empty($dateIndex)) {
            $this->warn('Need at least 30 days of data to generate rate report.');

            return;
        }

        // Compute slope for every date
        $allSlopes = [];
        foreach ($dateIndex as $date) {
            $result = $this->computeRateAt($date, $sorted);
            if ($result !== null) {
                $allSlopes[] = array_merge(['date' => $date], $result);
            }
        }

        if (empty($allSlopes)) {
            $this->warn('Could not compute any rates.');

            return;
        }

        // Current rate (latest)
        $current = end($allSlopes);

        // Stats
        $slopes = array_column($allSlopes, 'slope');
        $avgSlope = array_sum($slopes) / count($slopes);

        $bestIdx = 0;
        $worstIdx = 0;
        for ($i = 1; $i < count($slopes); $i++) {
            if ($slopes[$i] < $slopes[$bestIdx]) {
                $bestIdx = $i;
            }
            if ($slopes[$i] > $slopes[$worstIdx]) {
                $worstIdx = $i;
            }
        }

        $best = $allSlopes[$bestIdx];
        $worst = $allSlopes[$worstIdx];

        $losingCount = count(array_filter($slopes, fn ($s) => $s < -0.001));
        $gainingCount = count(array_filter($slopes, fn ($s) => $s > 0.001));
        $stableCount = count($slopes) - $losingCount - $gainingCount;
        $total = count($slopes);

        // Current streak
        $currentTrend = null;
        $streakCount = 0;
        for ($i = count($slopes) - 1; $i >= 0; $i--) {
            $trend = $slopes[$i] < -0.001 ? 'losing' : ($slopes[$i] > 0.001 ? 'gaining' : 'stable');
            if ($currentTrend === null) {
                $currentTrend = $trend;
            }
            if ($trend === $currentTrend) {
                $streakCount++;
            } else {
                break;
            }
        }

        // Build output
        $lines = [];
        $lines[] = '╔══════════════════════════════════════════╗';
        $lines[] = '║        WEIGHT RATE REPORT                ║';
        $lines[] = '║        '.now()->format('d M Y').'                       ║';
        $lines[] = '╚══════════════════════════════════════════╝';
        $lines[] = '';

        // Current rate
        $lines[] = '── Current Rate ('.($current['date']->format('d M Y')).') ──';
        $lines[] = '';
        $lines[] = '  Daily:   '.$this->formatSlope($current['slope']).' kg/day';
        $lines[] = '  Weekly:  '.$this->formatSlope($current['slope'] * 7).' kg/week';
        $lines[] = '  Avg weight in window: '.round($current['avgWeight'], 1).' kg';
        $lines[] = '  Entries in window: '.$current['pointCount'];
        $lines[] = '  Trend: '.ucfirst($this->getTrend($current['slope']));
        $lines[] = '';

        // Historical summary
        $lines[] = '── Historical Summary ──';
        $lines[] = '';
        $lines[] = '  Best rate:    '.$this->formatSlope($best['slope']).' kg/day ('.$this->formatSlope($best['slope'] * 7).' kg/week)';
        $lines[] = '                '.$best['date']->format('d M Y');
        $lines[] = '';
        $lines[] = '  Worst rate:   '.$this->formatSlope($worst['slope']).' kg/day ('.$this->formatSlope($worst['slope'] * 7).' kg/week)';
        $lines[] = '                '.$worst['date']->format('d M Y');
        $lines[] = '';
        $lines[] = '  Average rate: '.$this->formatSlope($avgSlope).' kg/day ('.$this->formatSlope($avgSlope * 7).' kg/week)';
        $lines[] = '                across '.$total.' windows';
        $lines[] = '';

        // Distribution
        $lines[] = '── Trend Distribution ──';
        $lines[] = '';
        $losingPct = round(($losingCount / $total) * 100);
        $gainingPct = round(($gainingCount / $total) * 100);
        $stablePct = 100 - $losingPct - $gainingPct;

        $barWidth = 30;
        $losingBar = (int) round($losingPct / 100 * $barWidth);
        $gainingBar = (int) round($gainingPct / 100 * $barWidth);
        $stableBar = $barWidth - $losingBar - $gainingBar;

        $bar = str_repeat('▓', $losingBar).str_repeat('░', $stableBar).str_repeat('▒', $gainingBar);
        $lines[] = '  ['.$bar.']';
        $lines[] = '  ▓ Losing: '.$losingPct.'% ('.$losingCount.' windows)';
        if ($stableCount > 0) {
            $lines[] = '  ░ Stable: '.$stablePct.'% ('.$stableCount.' windows)';
        }
        $lines[] = '  ▒ Gaining: '.$gainingPct.'% ('.$gainingCount.' windows)';
        $lines[] = '';

        // Streak
        $lines[] = '── Current Streak ──';
        $lines[] = '';
        $trendLabel = match ($currentTrend) {
            'losing' => 'losing weight',
            'gaining' => 'gaining weight',
            default => 'stable',
        };
        $lines[] = '  '.$streakCount.' consecutive window'.($streakCount !== 1 ? 's' : '').' '.$trendLabel;
        $lines[] = '';

        // Transformation Summary
        $startWeight = $sorted[0]['weight'];
        $startDate = $sorted[0]['date'];
        $endWeight = end($sorted)['weight'];
        $endDate = end($sorted)['date'];
        $totalLoss = $endWeight - $startWeight;
        $durationDays = $startDate->diffInDays($endDate);
        $durationWeeks = $durationDays / 7;
        $durationMonths = $durationDays / 30.44;
        $avgWeeklyLoss = $durationWeeks > 0 ? $totalLoss / $durationWeeks : 0;
        $avgMonthlyLoss = $durationMonths > 0 ? $totalLoss / $durationMonths : 0;
        $heightM = 1.75;
        $startBMI = round($startWeight / ($heightM * $heightM), 1);
        $endBMI = round($endWeight / ($heightM * $heightM), 1);
        $pctLoss = $startWeight > 0 ? round(abs($totalLoss) / $startWeight * 100, 1) : 0;

        $lines[] = '── Transformation Summary ──';
        $lines[] = '';
        $lines[] = '  Start weight:      '.number_format($startWeight, 1).' kg (BMI '.$startBMI.') on '.$startDate->format('d M Y');
        $lines[] = '  Current weight:    '.number_format($endWeight, 1).' kg (BMI '.$endBMI.') on '.$endDate->format('d M Y');
        $lines[] = '  Total change:      '.$this->formatSlope($totalLoss).' kg ('.($totalLoss < 0 ? '-' : '+').$pctLoss.'% body weight)';
        $lines[] = '  Duration:          '.$durationDays.' days ('.round($durationWeeks, 1).' weeks)';
        $lines[] = '  Avg weekly loss:   '.$this->formatSlope($avgWeeklyLoss).' kg/week';
        $lines[] = '  Avg monthly loss:  '.$this->formatSlope($avgMonthlyLoss).' kg/month';
        $avgDailyDeficit = $durationDays > 0 ? round(abs($totalLoss) / $durationDays * 7700) : 0;
        $lines[] = '  Avg daily deficit: ~'.$avgDailyDeficit.' kcal/day (based on 7700 kcal/kg)';
        $lines[] = '';

        // Progress Milestones
        $milestones = $this->computeMilestones($sorted, $startWeight);
        if (! empty($milestones)) {
            $lines[] = '── Progress Milestones ──';
            $lines[] = '';
            foreach ($milestones as $milestone) {
                $lines[] = '  '.$milestone['label'].': '.$milestone['date']->format('d M Y').' ('.$milestone['days'].' days in)';
            }
            $lines[] = '';
        }

        // Plateau Detection
        $plateaus = $this->detectPlateaus($sorted);
        $lines[] = '── Plateau Analysis ──';
        $lines[] = '';
        if (! empty($plateaus)) {
            $longestPlateau = max(array_column($plateaus, 'days'));
            $avgPlateauLen = round(array_sum(array_column($plateaus, 'days')) / count($plateaus), 1);
            $lastPlateau = end($plateaus);
            $isCurrentlyInPlateau = $lastPlateau['end']->eq($endDate) || $lastPlateau['end']->diffInDays($endDate) <= 1;

            $lines[] = '  Longest plateau:   '.$longestPlateau.' days';
            $lines[] = '  Average length:    '.$avgPlateauLen.' days';
            $lines[] = '  Total plateaus:    '.count($plateaus);
            $lines[] = '  Current plateau:   '.($isCurrentlyInPlateau ? 'YES ('.$lastPlateau['days'].' days, since '.$lastPlateau['start']->format('d M Y').')' : 'none');
            $lines[] = '';

            if (count($plateaus) <= 10) {
                foreach ($plateaus as $p) {
                    $lines[] = '    '.$p['start']->format('d M Y').' - '.$p['end']->format('d M Y')
                        .'  '.$p['days'].' days  ~'.number_format($p['avgWeight'], 1).' kg';
                }
            } else {
                // Show first 3 and last 3
                foreach (array_slice($plateaus, 0, 3) as $p) {
                    $lines[] = '    '.$p['start']->format('d M Y').' - '.$p['end']->format('d M Y')
                        .'  '.$p['days'].' days  ~'.number_format($p['avgWeight'], 1).' kg';
                }
                $lines[] = '    ... '.count($plateaus) - 6 .' more ...';
                foreach (array_slice($plateaus, -3) as $p) {
                    $lines[] = '    '.$p['start']->format('d M Y').' - '.$p['end']->format('d M Y')
                        .'  '.$p['days'].' days  ~'.number_format($p['avgWeight'], 1).' kg';
                }
            }
        } else {
            $lines[] = '  No plateaus detected (|change| < 0.2 kg over 5+ days)';
        }
        $lines[] = '';

        // Whoosh Detection
        $whooshes = $this->detectWhooshes($sorted, $plateaus);
        $lines[] = '── Whoosh Events ──';
        $lines[] = '';
        if (! empty($whooshes)) {
            $largestWhoosh = min(array_column($whooshes, 'drop'));
            $largestIdx = array_search($largestWhoosh, array_column($whooshes, 'drop'));
            $lastWhoosh = end($whooshes);

            $lines[] = '  Largest whoosh:    '.number_format($largestWhoosh, 1).' kg in '.$whooshes[$largestIdx]['days'].' days';
            $lines[] = '  Events detected:   '.count($whooshes);
            $lines[] = '  Last whoosh:       '.$lastWhoosh['date']->format('d M Y');
            $lines[] = '';

            foreach ($whooshes as $w) {
                $lines[] = '    '.$w['date']->format('d M Y').'  '.number_format($w['drop'], 1).' kg in '.$w['days'].' days';
            }
        } else {
            $lines[] = '  No whoosh events detected (drop >= 1.5 kg within 7 days after plateau)';
        }
        $lines[] = '';

        // Volatility / Fluctuation Metrics
        $volatility = $this->computeVolatility($sorted);
        $lines[] = '── Weight Stability ──';
        $lines[] = '';
        $lines[] = '  Daily volatility (std dev): '.number_format($volatility['stdDev'], 2).' kg';
        $lines[] = '  Largest single-day drop:    '.number_format($volatility['maxDrop'], 1).' kg ('.$volatility['maxDropDate']->format('d M Y').')';
        $lines[] = '  Largest single-day gain:    +'.number_format($volatility['maxGain'], 1).' kg ('.$volatility['maxGainDate']->format('d M Y').')';
        $lines[] = '';

        // Maintenance Projection (only if rate is slowing down / near zero)
        if (abs($current['slope']) < 0.05) {
            $recentAvg = $current['avgWeight'];
            $lines[] = '── Maintenance Projection ──';
            $lines[] = '';
            $lines[] = '  Estimated maintenance range: '.number_format($recentAvg - 1.0, 1).' - '.number_format($recentAvg + 1.0, 1).' kg';
            $maintenanceBMI = round($recentAvg / ($heightM * $heightM), 1);
            $lines[] = '  Maintenance BMI:             '.$maintenanceBMI;
            // Mifflin-St Jeor estimate (male, age ~30, 175cm)
            $maintenanceCal = round(10 * $recentAvg + 6.25 * 175 - 5 * 30 + 5);
            $lines[] = '  Est. maintenance calories:   ~'.$maintenanceCal.' kcal/day (sedentary)';
            $lines[] = '';
        }

        // Weekly breakdown — one row per week since tracking began
        $lines[] = '── Weekly Breakdown ──';
        $lines[] = '';
        $lines[] = '  Week                        Start     End       Change    Daily       Weekly      Entries';
        $lines[] = '  ──────────────────────────  ────────  ────────  ────────  ──────────  ──────────  ───────';

        $weeks = $this->computeWeeklyBreakdown($sorted);

        foreach ($weeks as $week) {
            $weekLabel = str_pad($week['start']->format('d M Y').' - '.$week['end']->format('d M Y'), 28);
            $startW = str_pad(number_format($week['startWeight'], 1).'kg', 9);
            $endW = str_pad(number_format($week['endWeight'], 1).'kg', 9);
            $change = $week['endWeight'] - $week['startWeight'];
            $changeStr = str_pad($this->formatSlope($change).' kg', 9);
            $dailyStr = str_pad($this->formatSlope($week['dailyRate']).' kg', 11);
            $weeklyStr = str_pad($this->formatSlope($week['dailyRate'] * 7).' kg', 11);
            $entriesStr = (string) $week['entries'];
            $lines[] = '  '.$weekLabel.'  '.$startW.' '.$endW.' '.$changeStr.' '.$dailyStr.' '.$weeklyStr.' '.$entriesStr;
        }

        $lines[] = '';
        $lines[] = '──────────────────────────────────────────';

        $output = implode("\n", $lines);

        if ($this->option('pdf')) {
            $this->generatePdf(
                $sorted,
                $current,
                $best,
                $worst,
                $avgSlope,
                $total,
                $losingCount,
                $gainingCount,
                $stableCount,
                $losingPct,
                $gainingPct,
                $stablePct,
                $streakCount,
                $currentTrend,
                $milestones,
                $plateaus,
                $whooshes,
                $volatility,
                $weeks
            );

            return;
        }

        $this->line($output);

        // Also copy to clipboard on macOS
        $process = proc_open('pbcopy', [['pipe', 'r']], $pipes);
        if (is_resource($process)) {
            fwrite($pipes[0], $output);
            fclose($pipes[0]);
            proc_close($process);
            $this->newLine();
            $this->info('Report copied to clipboard!');
        }
    }

    private function generatePdf(
        array $sorted,
        array $current,
        array $best,
        array $worst,
        float $avgSlope,
        int $total,
        int $losingCount,
        int $gainingCount,
        int $stableCount,
        int $losingPct,
        int $gainingPct,
        int $stablePct,
        int $streakCount,
        ?string $currentTrend,
        array $milestones,
        array $plateaus,
        array $whooshes,
        array $volatility,
        array $weeks,
    ): void {
        $startWeight = $sorted[0]['weight'];
        $startDate = $sorted[0]['date'];
        $endWeight = end($sorted)['weight'];
        $endDate = end($sorted)['date'];
        $totalLoss = $endWeight - $startWeight;
        $durationDays = $startDate->diffInDays($endDate);
        $durationWeeks = $durationDays / 7;
        $durationMonths = $durationDays / 30.44;
        $heightM = 1.75;

        $fmtSlope = fn (float $v) => ($v < -0.001 ? '-' : ($v > 0.001 ? '+' : '')).number_format(abs($v), 3);

        $data = [
            'reportDate' => now()->format('d M Y'),

            'transformation' => [
                'startWeight' => number_format($startWeight, 1),
                'endWeight' => number_format($endWeight, 1),
                'startBMI' => round($startWeight / ($heightM * $heightM), 1),
                'endBMI' => round($endWeight / ($heightM * $heightM), 1),
                'startDate' => $startDate->format('d M Y'),
                'endDate' => $endDate->format('d M Y'),
                'totalChange' => number_format($totalLoss, 1),
                'pctLoss' => $startWeight > 0 ? round(abs($totalLoss) / $startWeight * 100, 1) : 0,
                'durationDays' => $durationDays,
                'avgWeeklyLoss' => $fmtSlope($durationWeeks > 0 ? $totalLoss / $durationWeeks : 0),
                'avgMonthlyLoss' => $fmtSlope($durationMonths > 0 ? $totalLoss / $durationMonths : 0),
                'avgDailyDeficit' => $durationDays > 0 ? number_format(round(abs($totalLoss) / $durationDays * 7700)) : '0',
            ],

            'funFacts' => $this->generateFunFacts(abs($totalLoss), $durationDays, $endWeight),

            'currentRate' => [
                'daily' => $fmtSlope($current['slope']),
                'weekly' => $fmtSlope($current['slope'] * 7),
                'date' => $current['date']->format('d M Y'),
                'entries' => $current['pointCount'],
            ],

            'historicalSummary' => [
                'bestDaily' => $fmtSlope($best['slope']),
                'bestWeekly' => $fmtSlope($best['slope'] * 7),
                'bestDate' => $best['date']->format('d M Y'),
                'worstDaily' => $fmtSlope($worst['slope']),
                'worstWeekly' => $fmtSlope($worst['slope'] * 7),
                'worstDate' => $worst['date']->format('d M Y'),
                'avgDaily' => $fmtSlope($avgSlope),
                'avgWeekly' => $fmtSlope($avgSlope * 7),
                'totalWindows' => $total,
            ],

            'distribution' => [
                'losingPct' => $losingPct,
                'gainingPct' => $gainingPct,
                'stablePct' => $stablePct,
                'losingCount' => $losingCount,
                'gainingCount' => $gainingCount,
                'stableCount' => $stableCount,
            ],

            'streak' => [
                'count' => $streakCount,
                'label' => match ($currentTrend) {
                    'losing' => 'losing weight',
                    'gaining' => 'gaining weight',
                    default => 'stable',
                },
            ],

            'milestones' => array_map(fn ($m) => [
                'label' => $m['label'],
                'date' => $m['date']->format('d M Y'),
                'days' => $m['days'],
            ], $milestones),

            'plateaus' => array_map(fn ($p) => [
                'start' => $p['start']->format('d M Y'),
                'end' => $p['end']->format('d M Y'),
                'days' => $p['days'],
                'avgWeight' => number_format($p['avgWeight'], 1),
            ], $plateaus),

            'plateauStats' => ! empty($plateaus) ? [
                'longest' => max(array_column($plateaus, 'days')),
                'avg' => round(array_sum(array_column($plateaus, 'days')) / count($plateaus), 1),
                'currentlyInPlateau' => end($plateaus)['end']->eq($endDate) || end($plateaus)['end']->diffInDays($endDate) <= 1,
            ] : ['longest' => 0, 'avg' => 0, 'currentlyInPlateau' => false],

            'whooshes' => array_map(fn ($w) => [
                'date' => $w['date']->format('d M Y'),
                'drop' => number_format($w['drop'], 1),
                'days' => $w['days'],
            ], $whooshes),

            'whooshStats' => ! empty($whooshes) ? [
                'largest' => number_format(min(array_column($whooshes, 'drop')), 1),
            ] : ['largest' => 0],

            'volatility' => [
                'stdDev' => number_format($volatility['stdDev'], 2),
                'maxDrop' => number_format($volatility['maxDrop'], 1),
                'maxDropDate' => $volatility['maxDropDate']->format('d M Y'),
                'maxGain' => number_format($volatility['maxGain'], 1),
                'maxGainDate' => $volatility['maxGainDate']->format('d M Y'),
            ],

            'weeks' => array_map(function ($week) use ($fmtSlope) {
                $change = $week['endWeight'] - $week['startWeight'];

                return [
                    'period' => $week['start']->format('d M Y').' - '.$week['end']->format('d M Y'),
                    'startWeight' => number_format($week['startWeight'], 1),
                    'endWeight' => number_format($week['endWeight'], 1),
                    'change' => $fmtSlope($change),
                    'dailyRate' => $fmtSlope($week['dailyRate']),
                    'weeklyRate' => $fmtSlope($week['dailyRate'] * 7),
                    'entries' => $week['entries'],
                    'changeColor' => $change < -0.001 ? '#16a34a' : ($change > 0.001 ? '#dc2626' : '#64748b'),
                ];
            }, $weeks),
        ];

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.rate-report', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'weight-rate-report-'.now()->format('Y-m-d').'.pdf';
        $path = $_SERVER['HOME'].'/Downloads/'.$filename;
        $pdf->save($path);

        $this->info('PDF saved to: '.$path);

        // Open in default viewer on macOS
        exec('open '.escapeshellarg($path));
    }

    private function computeRateAt(Carbon $targetDate, array $sorted): ?array
    {
        $windowStart = $targetDate->copy()->subDays(self::WINDOW_DAYS);

        $points = [];
        foreach ($sorted as $entry) {
            if ($entry['date']->gte($windowStart) && $entry['date']->lte($targetDate)) {
                $points[] = [
                    'dayOffset' => $windowStart->diffInDays($entry['date']),
                    'weight' => $entry['weight'],
                ];
            }
        }

        if (count($points) < 2) {
            return null;
        }

        $slope = $this->linearRegressionSlope($points);

        $avgWeight = array_sum(array_column($points, 'weight')) / count($points);

        return [
            'slope' => $slope,
            'avgWeight' => $avgWeight,
            'pointCount' => count($points),
        ];
    }

    private function linearRegressionSlope(array $points): float
    {
        $n = count($points);
        $sumX = $sumY = $sumXY = $sumXX = 0;

        foreach ($points as $p) {
            $sumX += $p['dayOffset'];
            $sumY += $p['weight'];
            $sumXY += $p['dayOffset'] * $p['weight'];
            $sumXX += $p['dayOffset'] * $p['dayOffset'];
        }

        $denom = $n * $sumXX - $sumX * $sumX;
        if (abs($denom) < 1e-10) {
            return 0.0;
        }

        return ($n * $sumXY - $sumX * $sumY) / $denom;
    }

    private function formatSlope(float $slope): string
    {
        $sign = $slope < -0.001 ? '-' : ($slope > 0.001 ? '+' : ' ');

        return $sign.number_format(abs($slope), 3);
    }

    private function computeWeeklyBreakdown(array $sorted): array
    {
        if (count($sorted) < 2) {
            return [];
        }

        $firstDate = $sorted[0]['date']->copy()->startOfWeek(Carbon::MONDAY);
        $lastDate = end($sorted)['date'];

        $weeks = [];
        $weekStart = $firstDate->copy();

        while ($weekStart->lte($lastDate)) {
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
            if ($weekEnd->gt($lastDate)) {
                $weekEnd = $lastDate->copy();
            }

            // Gather entries in this week
            $weekEntries = array_filter($sorted, function ($e) use ($weekStart, $weekEnd) {
                return $e['date']->gte($weekStart) && $e['date']->lte($weekEnd);
            });

            if (count($weekEntries) >= 1) {
                $weekEntries = array_values($weekEntries);

                // Sort by date to get first/last
                usort($weekEntries, fn ($a, $b) => $a['date']->timestamp - $b['date']->timestamp);

                $startWeight = $weekEntries[0]['weight'];
                $endWeight = end($weekEntries)['weight'];

                // Compute daily rate via regression if we have 2+ entries
                $dailyRate = 0.0;
                if (count($weekEntries) >= 2) {
                    $points = [];
                    $refDate = $weekEntries[0]['date'];
                    foreach ($weekEntries as $e) {
                        $points[] = [
                            'dayOffset' => $refDate->diffInDays($e['date']),
                            'weight' => $e['weight'],
                        ];
                    }
                    $dailyRate = $this->linearRegressionSlope($points);
                } elseif (count($weekEntries) === 1 && count($weeks) > 0) {
                    // Single entry week: compute rate from previous week's end weight
                    $prevWeek = end($weeks);
                    $daysDiff = $prevWeek['end']->diffInDays($weekEntries[0]['date']);
                    if ($daysDiff > 0) {
                        $dailyRate = ($endWeight - $prevWeek['endWeight']) / $daysDiff;
                    }
                }

                $weeks[] = [
                    'start' => $weekStart->copy(),
                    'end' => min($weekEnd, end($weekEntries)['date'])->copy(),
                    'startWeight' => $startWeight,
                    'endWeight' => $endWeight,
                    'dailyRate' => $dailyRate,
                    'entries' => count($weekEntries),
                ];
            }

            $weekStart = $weekStart->copy()->addWeek();
        }

        return $weeks;
    }

    /**
     * Detect milestones: every 10kg lost from starting weight, plus round number crossings.
     */
    private function computeMilestones(array $sorted, float $startWeight): array
    {
        $milestones = [];
        $startDate = $sorted[0]['date'];

        // Every 10kg lost milestone
        $nextMilestone = 10;
        $totalLost = 0;
        $prevWeight = $startWeight;

        // Round number crossings (e.g. dropping below 130, 120, 110, etc.)
        $nextRound = floor($startWeight / 10) * 10;
        $roundCrossings = [];

        foreach ($sorted as $entry) {
            $currentLoss = $startWeight - $entry['weight'];

            // 10kg milestones
            while ($currentLoss >= $nextMilestone) {
                $milestones[] = [
                    'label' => '-'.$nextMilestone.' kg',
                    'date' => $entry['date'],
                    'days' => $startDate->diffInDays($entry['date']),
                ];
                $nextMilestone += 10;
            }

            // Round number crossings
            while ($entry['weight'] < $nextRound && $nextRound < $startWeight) {
                $roundCrossings[] = [
                    'label' => 'Below '.(int) $nextRound.' kg',
                    'date' => $entry['date'],
                    'days' => $startDate->diffInDays($entry['date']),
                ];
                $nextRound -= 10;
            }
        }

        // Merge and sort by days
        $all = array_merge($milestones, $roundCrossings);
        usort($all, fn ($a, $b) => $a['days'] - $b['days']);

        // Deduplicate same-day milestones — keep both but avoid noise
        return $all;
    }

    /**
     * Detect plateaus: periods where |weight change| < 0.2 kg over 5+ consecutive days.
     */
    private function detectPlateaus(array $sorted): array
    {
        $plateaus = [];
        $plateauStart = null;
        $plateauWeight = null;
        $plateauWeights = [];

        for ($i = 0; $i < count($sorted); $i++) {
            if ($plateauStart === null) {
                $plateauStart = $i;
                $plateauWeight = $sorted[$i]['weight'];
                $plateauWeights = [$sorted[$i]['weight']];

                continue;
            }

            $diff = abs($sorted[$i]['weight'] - $plateauWeight);

            if ($diff < 0.5) {
                // Still on plateau — use a wider tolerance of 0.5kg from the start weight
                $plateauWeights[] = $sorted[$i]['weight'];
            } else {
                // Plateau broken — check if it was long enough
                $days = $sorted[$plateauStart]['date']->diffInDays($sorted[$i - 1]['date']);
                if ($days >= 5 && count($plateauWeights) >= 3) {
                    $avgW = array_sum($plateauWeights) / count($plateauWeights);
                    $plateaus[] = [
                        'start' => $sorted[$plateauStart]['date'],
                        'end' => $sorted[$i - 1]['date'],
                        'days' => $days,
                        'avgWeight' => $avgW,
                    ];
                }

                // Restart
                $plateauStart = $i;
                $plateauWeight = $sorted[$i]['weight'];
                $plateauWeights = [$sorted[$i]['weight']];
            }
        }

        // Check final plateau
        if ($plateauStart !== null) {
            $lastIdx = count($sorted) - 1;
            $days = $sorted[$plateauStart]['date']->diffInDays($sorted[$lastIdx]['date']);
            if ($days >= 5 && count($plateauWeights) >= 3) {
                $avgW = array_sum($plateauWeights) / count($plateauWeights);
                $plateaus[] = [
                    'start' => $sorted[$plateauStart]['date'],
                    'end' => $sorted[$lastIdx]['date'],
                    'days' => $days,
                    'avgWeight' => $avgW,
                ];
            }
        }

        return $plateaus;
    }

    /**
     * Detect whoosh events: rapid weight drops (>= 1.5 kg within 7 days) after a plateau.
     */
    private function detectWhooshes(array $sorted, array $plateaus): array
    {
        $whooshes = [];

        foreach ($plateaus as $plateau) {
            // Find entries in the 7 days after the plateau ended
            $afterStart = $plateau['end'];
            $afterEnd = $plateau['end']->copy()->addDays(7);

            $postEntries = array_filter($sorted, function ($e) use ($afterStart, $afterEnd) {
                return $e['date']->gt($afterStart) && $e['date']->lte($afterEnd);
            });

            if (empty($postEntries)) {
                continue;
            }

            $postEntries = array_values($postEntries);

            // Find lowest weight in post-plateau window
            $minPostWeight = min(array_column($postEntries, 'weight'));
            $drop = $minPostWeight - $plateau['avgWeight'];

            if ($drop <= -1.5) {
                // Find the date of the minimum
                $minEntry = null;
                foreach ($postEntries as $e) {
                    if ($e['weight'] === $minPostWeight) {
                        $minEntry = $e;

                        break;
                    }
                }

                $days = $plateau['end']->diffInDays($minEntry['date']);

                $whooshes[] = [
                    'date' => $minEntry['date'],
                    'drop' => $drop,
                    'days' => $days,
                    'fromWeight' => $plateau['avgWeight'],
                    'toWeight' => $minPostWeight,
                ];
            }
        }

        return $whooshes;
    }

    /**
     * Compute volatility metrics: std dev of day-to-day changes,
     * largest single-day drop and gain.
     */
    private function computeVolatility(array $sorted): array
    {
        $changes = [];
        $maxDrop = 0;
        $maxDropDate = $sorted[0]['date'];
        $maxGain = 0;
        $maxGainDate = $sorted[0]['date'];

        // Build daily lowest weights first
        $dailyLowest = [];
        foreach ($sorted as $entry) {
            $key = $entry['date']->format('Y-m-d');
            if (! isset($dailyLowest[$key]) || $entry['weight'] < $dailyLowest[$key]['weight']) {
                $dailyLowest[$key] = $entry;
            }
        }

        $days = array_values($dailyLowest);
        usort($days, fn ($a, $b) => $a['date']->timestamp - $b['date']->timestamp);

        for ($i = 1; $i < count($days); $i++) {
            $change = $days[$i]['weight'] - $days[$i - 1]['weight'];
            $changes[] = $change;

            if ($change < $maxDrop) {
                $maxDrop = $change;
                $maxDropDate = $days[$i]['date'];
            }
            if ($change > $maxGain) {
                $maxGain = $change;
                $maxGainDate = $days[$i]['date'];
            }
        }

        // Standard deviation
        $stdDev = 0;
        if (count($changes) > 1) {
            $mean = array_sum($changes) / count($changes);
            $variance = 0;
            foreach ($changes as $c) {
                $variance += ($c - $mean) ** 2;
            }
            $stdDev = sqrt($variance / (count($changes) - 1));
        }

        return [
            'stdDev' => $stdDev,
            'maxDrop' => $maxDrop,
            'maxDropDate' => $maxDropDate,
            'maxGain' => $maxGain,
            'maxGainDate' => $maxGainDate,
        ];
    }

    private function generateFunFacts(float $kgLost, int $durationDays, float $currentWeight): array
    {
        $lbsLost = round($kgLost * 2.205, 1);

        // Weight equivalents — pick all that apply
        $equivalents = [];

        if ($kgLost >= 5) {
            $equivalents[] = number_format(round($kgLost / 0.45)).' sticks of butter';
        }
        if ($kgLost >= 10) {
            $equivalents[] = 'a medium dog ('.round($kgLost).' kg)';
        }
        if ($kgLost >= 15) {
            $equivalents[] = round($kgLost / 5).' bowling balls';
        }
        if ($kgLost >= 20) {
            $equivalents[] = 'a car tire (per 10 kg x '.round($kgLost / 10).')';
        }
        if ($kgLost >= 23) {
            $equivalents[] = 'a maximum airline checked bag ('.round($kgLost / 23, 1).'x)';
        }
        if ($kgLost >= 25) {
            $equivalents[] = round($kgLost / 12.7).' standard cinder blocks';
        }
        if ($kgLost >= 30) {
            $equivalents[] = 'an average 10-year-old child';
        }
        if ($kgLost >= 40) {
            $equivalents[] = 'a large bale of hay';
        }
        if ($kgLost >= 50) {
            $equivalents[] = 'a full-size adult bicycle x'.round($kgLost / 10);
        }
        if ($kgLost >= 55) {
            $equivalents[] = 'an average-sized adult human';
        }

        // Body / health impact
        $bodyImpact = [];

        // Knee load: each kg of body weight = ~4x load on knees per step
        // Average 6000 steps/day
        $kneeLoadPerDay = $kgLost * 4 * 6000; // kg of cumulative load removed per day
        $kneeLoadTons = round($kneeLoadPerDay / 1000, 0);
        $bodyImpact[] = '~'.number_format($kneeLoadTons).' tonnes less stress on your knees per day (~6000 steps)';

        // Total caloric deficit
        $totalDeficit = round($kgLost * 7700);
        $pizzas = round($totalDeficit / 2000);
        $bodyImpact[] = number_format($totalDeficit).' kcal total deficit (~'.number_format($pizzas).' whole pizzas worth of energy)';

        // Fat volume: 1 kg of fat ~ 1.1 liters
        $liters = round($kgLost * 1.1, 1);
        $bodyImpact[] = $liters.'L of body fat removed (~'.round($liters / 0.33).' cans of soda in volume)';

        // Steps equivalent to burn it all running
        // ~60 kcal per km running at avg weight, 7700 kcal per kg
        $avgWeight = $currentWeight + ($kgLost / 2);
        $calPerKm = $avgWeight * 0.75; // rough kcal per km
        $totalKm = round($totalDeficit / $calPerKm);
        $marathons = round($totalKm / 42.2, 1);
        $bodyImpact[] = 'Equivalent energy of running '.number_format($totalKm).' km ('.number_format($marathons, 1).' marathons)';

        // Heart beats saved
        // Overweight heart beats ~10 more times/min. Lost weight reduces this
        $extraBeatsPerMin = min($kgLost * 0.5, 15); // ~0.5 bpm per kg, cap at 15
        $beatsSaved = round($extraBeatsPerMin * 60 * 24 * $durationDays);
        $bodyImpact[] = '~'.number_format($beatsSaved).' fewer heartbeats over '.$durationDays.' days';

        return [
            'equivalents' => $equivalents,
            'bodyImpact' => $bodyImpact,
        ];
    }

    private function getTrend(float $slope): string
    {
        if ($slope < -0.001) {
            return 'losing';
        }
        if ($slope > 0.001) {
            return 'gaining';
        }

        return 'stable';
    }
}
