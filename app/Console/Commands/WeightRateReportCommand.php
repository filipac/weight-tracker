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

    // Personal / maintenance settings
    private const HEIGHT_CM = 173;

    private const AGE = 33;

    private const TARGET_WEIGHT = 73.5;

    private const MAINTENANCE_MIN = 72.5;

    private const MAINTENANCE_MAX = 75.0;

    // Treat changes smaller than +/-0.25 kg/week as stable.
    private const STABLE_WEEKLY_THRESHOLD = 0.25;

    // Width (in characters) of the chronological trend bar in the text report.
    private const TIMELINE_BAR_WIDTH = 40;

    // Phases listed under the text timeline before collapsing into a summary line.
    private const TIMELINE_PHASE_LIMIT = 15;

    // Mifflin-St Jeor BMR x 1.2 = sedentary TDEE estimate.
    // Actual maintenance can be higher depending on activity.
    private const SEDENTARY_ACTIVITY_MULTIPLIER = 1.2;

    // Used only as a rough historical energy equivalent, not a measured deficit.
    private const ENERGY_EQUIVALENT_KCAL_PER_KG = 7700;

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

        $trends = array_map(fn ($s) => $this->getTrend($s), $slopes);
        $losingCount = count(array_filter($trends, fn ($trend) => $trend === 'losing'));
        $gainingCount = count(array_filter($trends, fn ($trend) => $trend === 'gaining'));
        $stableCount = count(array_filter($trends, fn ($trend) => $trend === 'stable'));
        $total = count($slopes);

        // Current streak
        $currentTrend = null;
        $streakCount = 0;
        for ($i = count($slopes) - 1; $i >= 0; $i--) {
            $trend = $this->getTrend($slopes[$i]);
            if ($currentTrend === null) {
                $currentTrend = $trend;
            }
            if ($trend === $currentTrend) {
                $streakCount++;
            } else {
                break;
            }
        }

        // Chronological trend phases (ordered, not just counted)
        $segments = $this->buildTrendSegments($allSlopes);
        $timelineDays = array_sum(array_column($segments, 'days'));

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

        // Timeline (chronological, oldest -> newest)
        $lines[] = '── Trend Timeline ──';
        $lines[] = '';
        $losingPct = round(($losingCount / $total) * 100);
        $gainingPct = round(($gainingCount / $total) * 100);
        $stablePct = 100 - $losingPct - $gainingPct;

        $bar = '';
        foreach ($this->allocateBarCells($segments, $timelineDays, self::TIMELINE_BAR_WIDTH) as $i => $cells) {
            $bar .= str_repeat($this->trendChar($segments[$i]['trend']), $cells);
        }

        $lines[] = '  ['.$bar.']';
        $lines[] = '  '.$segments[0]['start']->format('d M Y').' → '.end($segments)['end']->format('d M Y');
        $lines[] = '';
        $lines[] = '  ▓ Losing: '.$losingPct.'% ('.$losingCount.' windows)';
        if ($stableCount > 0) {
            $lines[] = '  ░ Stable: '.$stablePct.'% ('.$stableCount.' windows)';
        }
        $lines[] = '  ▒ Gaining: '.$gainingPct.'% ('.$gainingCount.' windows)';
        $lines[] = '';

        // Phases in order, so a gaining stretch in the middle stays visible
        $lines[] = '  Phases in order ('.count($segments).'):';
        foreach (array_slice($segments, 0, self::TIMELINE_PHASE_LIMIT) as $seg) {
            $lines[] = '    '.$this->trendChar($seg['trend']).' '
                .str_pad(ucfirst($seg['trend']), 8).' '
                .$seg['start']->format('d M Y').' → '.$seg['end']->format('d M Y')
                .'  ('.$seg['days'].' day'.($seg['days'] !== 1 ? 's' : '').', '.$this->formatSlope($seg['avgSlope']).' kg/day)';
        }
        if (count($segments) > self::TIMELINE_PHASE_LIMIT) {
            $lines[] = '    … and '.(count($segments) - self::TIMELINE_PHASE_LIMIT).' more';
        }
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
        $heightM = self::HEIGHT_CM / 100;
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
        $avgDailyEnergyEquivalent = $durationDays > 0
            ? round(abs($totalLoss) / $durationDays * self::ENERGY_EQUIVALENT_KCAL_PER_KG)
            : 0;
        $lines[] = '  Avg daily energy equivalent: ~'.$avgDailyEnergyEquivalent.' kcal/day (rough historical estimate)';
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

            if ($isCurrentlyInPlateau && $this->isInMaintenanceRange($endWeight)) {
                $lines[] = '  Current state:     MAINTENANCE STABILITY ('.$lastPlateau['days'].' days, since '.$lastPlateau['start']->format('d M Y').')';
            } else {
                $lines[] = '  Current plateau:   '.($isCurrentlyInPlateau ? 'YES ('.$lastPlateau['days'].' days, since '.$lastPlateau['start']->format('d M Y').')' : 'none');
            }
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
            $lines[] = '  No plateaus detected (within 0.5 kg of plateau start over 5+ days)';
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

        // Maintenance status
        $maintenance = $this->computeMaintenanceStats($sorted, $current);
        $lines[] = '── Maintenance Status ──';
        $lines[] = '';
        $lines[] = '  Target weight:                '.number_format(self::TARGET_WEIGHT, 1).' kg';
        $lines[] = '  Maintenance range:            '.number_format(self::MAINTENANCE_MIN, 1).' - '.number_format(self::MAINTENANCE_MAX, 1).' kg';
        $lines[] = '  Current status:               '.$maintenance['status'];
        $lines[] = '  30-day trend:                 '.$this->formatSlope($current['slope'] * 7).' kg/week ('.ucfirst($this->getTrend($current['slope'])).')';
        $lines[] = '  Current in-range streak:      '.$maintenance['streakDays'].' days / '.$maintenance['streakEntries'].' weigh-ins';
        $lines[] = '  Last 30 days in range:        '.$maintenance['last30InRangePct'].'%';
        $lines[] = '  BMR estimate:                 ~'.$maintenance['bmr'].' kcal/day';
        $lines[] = '  Sedentary maintenance est.:   ~'.$maintenance['sedentaryTdee'].' kcal/day';
        $lines[] = '  Note: actual maintenance can be higher with exercise and daily activity.';
        $lines[] = '';

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
            $dailyStr = str_pad($week['dailyRate'] !== null ? $this->formatSlope($week['dailyRate']).' kg' : 'n/a', 11);
            $weeklyStr = str_pad($week['dailyRate'] !== null ? $this->formatSlope($week['dailyRate'] * 7).' kg' : 'n/a', 11);
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
                $weeks,
                $segments,
                $timelineDays
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
        array $segments,
        int $timelineDays,
    ): void {
        $startWeight = $sorted[0]['weight'];
        $startDate = $sorted[0]['date'];
        $endWeight = end($sorted)['weight'];
        $endDate = end($sorted)['date'];
        $totalLoss = $endWeight - $startWeight;
        $durationDays = $startDate->diffInDays($endDate);
        $durationWeeks = $durationDays / 7;
        $durationMonths = $durationDays / 30.44;
        $heightM = self::HEIGHT_CM / 100;

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
                // Keep avgDailyDeficit for backwards compatibility with the existing Blade view.
                // It is an energy-equivalent estimate, not a directly measured physiological deficit.
                'avgDailyDeficit' => $durationDays > 0
                    ? number_format(round(abs($totalLoss) / $durationDays * self::ENERGY_EQUIVALENT_KCAL_PER_KG))
                    : '0',
                'avgDailyEnergyEquivalent' => $durationDays > 0
                    ? number_format(round(abs($totalLoss) / $durationDays * self::ENERGY_EQUIVALENT_KCAL_PER_KG))
                    : '0',
            ],

            'funFacts' => $this->generateFunFacts(abs($totalLoss), $durationDays, $endWeight),

            'currentRate' => [
                'daily' => $fmtSlope($current['slope']),
                'weekly' => $fmtSlope($current['slope'] * 7),
                'date' => $current['date']->format('d M Y'),
                'entries' => $current['pointCount'],
                'avgWeight' => number_format($current['avgWeight'], 1),
                'trend' => $this->getTrend($current['slope']),
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

            'timeline' => [
                'start' => $segments[0]['start']->format('d M Y'),
                'end' => end($segments)['end']->format('d M Y'),
                'phaseCount' => count($segments),
                // Widths are allocated over 200 half-percent cells so a one-window
                // phase still gets a visible sliver instead of rounding away.
                'segments' => array_values(array_map(
                    fn ($seg, $cells) => [
                        'trend' => $seg['trend'],
                        'widthPct' => round($cells / 2, 2),
                    ],
                    $segments,
                    $this->allocateBarCells($segments, $timelineDays, 200)
                )),
                // The non-losing stretches are the interesting part of the order;
                // show the longest few, kept in chronological order.
                'interruptions' => $this->longestInterruptions($segments),
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
                // Do not flag healthy in-range maintenance stability as a problematic plateau.
                'currentlyInPlateau' => (end($plateaus)['end']->eq($endDate) || end($plateaus)['end']->diffInDays($endDate) <= 1)
                    && ! $this->isInMaintenanceRange($endWeight),
                'maintenanceStability' => (end($plateaus)['end']->eq($endDate) || end($plateaus)['end']->diffInDays($endDate) <= 1)
                    && $this->isInMaintenanceRange($endWeight),
            ] : ['longest' => 0, 'avg' => 0, 'currentlyInPlateau' => false, 'maintenanceStability' => false],

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

            'maintenance' => $this->computeMaintenanceStats($sorted, $current),

            'weeks' => array_map(function ($week) use ($fmtSlope) {
                $change = $week['endWeight'] - $week['startWeight'];

                return [
                    'period' => $week['start']->format('d M Y').' - '.$week['end']->format('d M Y'),
                    'startWeight' => number_format($week['startWeight'], 1),
                    'endWeight' => number_format($week['endWeight'], 1),
                    'change' => $fmtSlope($change),
                    'dailyRate' => $week['dailyRate'] !== null ? $fmtSlope($week['dailyRate']) : 'n/a',
                    'weeklyRate' => $week['dailyRate'] !== null ? $fmtSlope($week['dailyRate'] * 7) : 'n/a',
                    'entries' => $week['entries'],
                    'changeColor' => $this->getWeekChangeColor($week['dailyRate'], $week['endWeight']),
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

                // Compute daily rate via regression only when the week has 2+ entries.
                // A single weigh-in is not enough to infer a meaningful weekly rate.
                $dailyRate = null;
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
     * Detect plateaus: periods staying within 0.5 kg of the plateau start over 5+ days.
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

        // Rough historical energy equivalent. This is intentionally not labelled as a
        // measured caloric deficit because scale weight includes water and lean mass too.
        $totalEnergyEquivalent = round($kgLost * self::ENERGY_EQUIVALENT_KCAL_PER_KG);
        $pizzas = round($totalEnergyEquivalent / 2000);
        $bodyImpact[] = '~'.number_format($totalEnergyEquivalent).' kcal weight-loss energy equivalent (~'.number_format($pizzas).' 2,000-kcal meals)';

        // A playful exercise-energy comparison, explicitly kept as a rough estimate.
        $avgWeight = $currentWeight + ($kgLost / 2);
        $calPerKm = max($avgWeight * 0.75, 1);
        $totalKm = round($totalEnergyEquivalent / $calPerKm);
        $marathons = round($totalKm / 42.2, 1);
        $bodyImpact[] = 'Rough running-energy equivalent: '.number_format($totalKm).' km ('.number_format($marathons, 1).' marathons)';

        return [
            'equivalents' => $equivalents,
            'bodyImpact' => $bodyImpact,
        ];
    }

    /**
     * Run-length encode the per-window slopes into chronological trend phases.
     * Each window is weighted by the calendar days until the next one, so a long
     * phase logged sparsely keeps its true share of the timeline.
     */
    private function buildTrendSegments(array $allSlopes): array
    {
        $segments = [];

        foreach ($allSlopes as $i => $entry) {
            $trend = $this->getTrend($entry['slope']);
            $next = $allSlopes[$i + 1] ?? null;
            $spanDays = $next !== null ? max(1, (int) $entry['date']->diffInDays($next['date'])) : 1;
            $lastIdx = count($segments) - 1;

            if ($lastIdx >= 0 && $segments[$lastIdx]['trend'] === $trend) {
                $segments[$lastIdx]['end'] = $entry['date'];
                $segments[$lastIdx]['windows']++;
                $segments[$lastIdx]['days'] += $spanDays;
                $segments[$lastIdx]['slopeSum'] += $entry['slope'];
            } else {
                $segments[] = [
                    'trend' => $trend,
                    'start' => $entry['date'],
                    'end' => $entry['date'],
                    'windows' => 1,
                    'days' => $spanDays,
                    'slopeSum' => $entry['slope'],
                ];
            }
        }

        return array_map(
            fn ($seg) => $seg + ['avgSlope' => $seg['slopeSum'] / $seg['windows']],
            $segments
        );
    }

    /**
     * Split $width bar cells across the segments proportionally to their days,
     * using largest-remainder so the cells always sum to exactly $width. Every
     * segment gets at least one cell when there is room, so short interruptions
     * never round away to nothing.
     */
    private function allocateBarCells(array $segments, int $timelineDays, int $width): array
    {
        if (empty($segments) || $width < 1) {
            return [];
        }

        $guaranteeMin = count($segments) <= $width;
        $counts = [];
        $remainders = [];
        $used = 0;

        foreach ($segments as $i => $seg) {
            $exact = $timelineDays > 0 ? $seg['days'] / $timelineDays * $width : 0;
            $cells = max($guaranteeMin ? 1 : 0, (int) floor($exact));
            $counts[$i] = $cells;
            $remainders[$i] = $exact - floor($exact);
            $used += $cells;
        }

        arsort($remainders);

        // Hand leftover cells to the biggest fractions first...
        while ($used < $width) {
            foreach (array_keys($remainders) as $i) {
                if ($used >= $width) {
                    break;
                }
                $counts[$i]++;
                $used++;
            }
        }

        // ...and claw back any overshoot from the widest segments.
        while ($used > $width) {
            $widest = null;
            foreach ($counts as $i => $cells) {
                if ($cells > 1 && ($widest === null || $cells > $counts[$widest])) {
                    $widest = $i;
                }
            }
            if ($widest === null) {
                break;
            }
            $counts[$widest]--;
            $used--;
        }

        ksort($counts);

        return $counts;
    }

    /**
     * The longest non-losing phases, capped and returned in chronological order.
     */
    private function longestInterruptions(array $segments, int $limit = 6): array
    {
        $breaks = array_filter($segments, fn ($seg) => $seg['trend'] !== 'losing');

        uasort($breaks, fn ($a, $b) => $b['days'] <=> $a['days']);
        $breaks = array_slice($breaks, 0, $limit, true);
        ksort($breaks);

        return array_values(array_map(fn ($seg) => [
            'trend' => $seg['trend'],
            'label' => ucfirst($seg['trend']).' '.$seg['days'].' day'.($seg['days'] !== 1 ? 's' : '')
                .': '.$seg['start']->format('d M Y').' to '.$seg['end']->format('d M Y'),
        ], $breaks));
    }

    private function trendChar(string $trend): string
    {
        return match ($trend) {
            'losing' => '▓',
            'gaining' => '▒',
            default => '░',
        };
    }

    private function getTrend(float $slope): string
    {
        $weeklySlope = $slope * 7;

        if ($weeklySlope < -self::STABLE_WEEKLY_THRESHOLD) {
            return 'losing';
        }

        if ($weeklySlope > self::STABLE_WEEKLY_THRESHOLD) {
            return 'gaining';
        }

        return 'stable';
    }

    private function isInMaintenanceRange(float $weight): bool
    {
        return $weight >= self::MAINTENANCE_MIN && $weight <= self::MAINTENANCE_MAX;
    }

    private function calculateBmr(float $weight): int
    {
        // Mifflin-St Jeor for a male: 10W + 6.25H - 5A + 5
        return (int) round(
            10 * $weight
            + 6.25 * self::HEIGHT_CM
            - 5 * self::AGE
            + 5
        );
    }

    private function calculateSedentaryTdee(float $weight): int
    {
        return (int) round($this->calculateBmr($weight) * self::SEDENTARY_ACTIVITY_MULTIPLIER);
    }

    private function computeMaintenanceStats(array $sorted, array $current): array
    {
        $endEntry = end($sorted);
        $endWeight = $endEntry['weight'];
        $endDate = $endEntry['date'];
        $trend = $this->getTrend($current['slope']);

        if ($endWeight < self::MAINTENANCE_MIN) {
            $status = $trend === 'losing' ? 'BELOW RANGE · DRIFTING DOWN' : 'BELOW RANGE';
        } elseif ($endWeight > self::MAINTENANCE_MAX) {
            $status = $trend === 'gaining' ? 'ABOVE RANGE · DRIFTING UP' : 'ABOVE RANGE';
        } else {
            $status = match ($trend) {
                'losing' => 'IN RANGE · DRIFTING DOWN',
                'gaining' => 'IN RANGE · DRIFTING UP',
                default => 'IN RANGE · STABLE',
            };
        }

        // Current consecutive run of measured entries inside the target range.
        $streakEntries = 0;
        $streakStart = null;
        for ($i = count($sorted) - 1; $i >= 0; $i--) {
            if (! $this->isInMaintenanceRange($sorted[$i]['weight'])) {
                break;
            }

            $streakEntries++;
            $streakStart = $sorted[$i]['date'];
        }

        $streakDays = $streakStart !== null ? $streakStart->diffInDays($endDate) + 1 : 0;

        // Percentage of weigh-ins in the last 30 calendar days that were inside the range.
        $last30Start = $endDate->copy()->subDays(29);
        $last30Entries = array_values(array_filter(
            $sorted,
            fn ($entry) => $entry['date']->gte($last30Start) && $entry['date']->lte($endDate)
        ));
        $last30InRange = count(array_filter(
            $last30Entries,
            fn ($entry) => $this->isInMaintenanceRange($entry['weight'])
        ));
        $last30InRangePct = count($last30Entries) > 0
            ? (int) round($last30InRange / count($last30Entries) * 100)
            : 0;

        return [
            'targetWeight' => number_format(self::TARGET_WEIGHT, 1),
            'minWeight' => number_format(self::MAINTENANCE_MIN, 1),
            'maxWeight' => number_format(self::MAINTENANCE_MAX, 1),
            'status' => $status,
            'trend' => $trend,
            'weeklyTrend' => $this->formatSlope($current['slope'] * 7),
            'streakDays' => $streakDays,
            'streakEntries' => $streakEntries,
            'last30InRangePct' => $last30InRangePct,
            'bmr' => $this->calculateBmr($current['avgWeight']),
            'sedentaryTdee' => $this->calculateSedentaryTdee($current['avgWeight']),
            'age' => self::AGE,
            'heightCm' => self::HEIGHT_CM,
        ];
    }

    private function getWeekChangeColor(?float $dailyRate, float $endWeight): string
    {
        // No rate inference from a single weigh-in.
        if ($dailyRate === null) {
            return '#64748b';
        }

        $trend = $this->getTrend($dailyRate);

        // Above the maintenance range: loss is useful, gain is a warning.
        if ($endWeight > self::MAINTENANCE_MAX) {
            return match ($trend) {
                'losing' => '#16a34a',
                'gaining' => '#dc2626',
                default => '#d97706',
            };
        }

        // Below the maintenance range: further loss is undesirable; recovery upward is useful.
        if ($endWeight < self::MAINTENANCE_MIN) {
            return match ($trend) {
                'losing' => '#dc2626',
                'gaining' => '#16a34a',
                default => '#d97706',
            };
        }

        // Inside the maintenance range, stability is the goal. Small movement either way is fine.
        return $trend === 'stable' ? '#16a34a' : '#0284c7';
    }
}
