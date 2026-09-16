<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            line-height: 1.4;
            padding: 30px;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 3px solid #6366f1;
        }
        .header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1e1b4b;
            letter-spacing: -0.5px;
        }
        .header .date {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }

        /* Transformation hero */
        .hero {
            background: #eef2ff;
            border: 1px solid #c7d2fe;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 16px;
            text-align: center;
        }
        .hero .big-number {
            font-size: 36px;
            font-weight: 800;
            color: #4338ca;
        }
        .hero .subtitle {
            font-size: 12px;
            color: #6366f1;
            margin-top: 2px;
        }
        .hero-stats {
            width: 100%;
            margin-top: 14px;
        }
        .hero-stats td {
            width: 33.3%;
            text-align: center;
            padding: 6px 4px;
        }
        .hero-stats .stat-value {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
        }
        .hero-stats .stat-label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Maintenance status */
        .maintenance-box {
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 18px;
            border: 1px solid #bbf7d0;
            background: #f0fdf4;
        }
        .maintenance-box.status-drift {
            border-color: #bae6fd;
            background: #f0f9ff;
        }
        .maintenance-box.status-alert {
            border-color: #fed7aa;
            background: #fff7ed;
        }
        .maintenance-status {
            font-size: 18px;
            font-weight: 800;
            color: #15803d;
            margin-bottom: 2px;
        }
        .status-drift .maintenance-status { color: #0369a1; }
        .status-alert .maintenance-status { color: #c2410c; }
        .maintenance-subtitle {
            font-size: 10px;
            color: #475569;
            margin-bottom: 10px;
        }
        .maintenance-table {
            width: 100%;
            border-collapse: collapse;
        }
        .maintenance-table td {
            width: 25%;
            text-align: center;
            padding: 5px 4px;
            vertical-align: top;
        }
        .maintenance-table .value {
            font-size: 15px;
            font-weight: 700;
            color: #1e293b;
        }
        .maintenance-table .label {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .maintenance-note {
            margin-top: 8px;
            font-size: 8px;
            color: #64748b;
            text-align: center;
        }

        /* Section titles */
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e1b4b;
            margin-top: 20px;
            margin-bottom: 10px;
            padding-bottom: 4px;
            border-bottom: 2px solid #e2e8f0;
        }

        /* Card grid */
        .cards {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
        }
        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 12px;
            vertical-align: top;
        }
        .card .card-label {
            font-size: 9px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .card .card-value {
            font-size: 16px;
            font-weight: 700;
        }
        .card .card-detail {
            font-size: 9px;
            color: #94a3b8;
            margin-top: 2px;
        }

        .green { color: #16a34a; }
        .red { color: #dc2626; }
        .blue { color: #2563eb; }
        .purple { color: #7c3aed; }
        .amber { color: #d97706; }
        .slate { color: #64748b; }

        .card-green { background: #f0fdf4; border-color: #bbf7d0; }
        .card-red { background: #fef2f2; border-color: #fecaca; }
        .card-blue { background: #eff6ff; border-color: #bfdbfe; }
        .card-purple { background: #f5f3ff; border-color: #ddd6fe; }
        .card-amber { background: #fffbeb; border-color: #fde68a; }

        /* Tables */
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9px;
            margin-bottom: 10px;
        }
        table.data-table th {
            background: #f1f5f9;
            padding: 6px 8px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #cbd5e1;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        table.data-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #f1f5f9;
        }
        table.data-table tr:nth-child(even) {
            background: #fafbfc;
        }

        /* Progress bar */
        .progress-bar {
            height: 14px;
            border-radius: 7px;
            overflow: hidden;
            background: #f1f5f9;
            margin: 6px 0;
        }
        .progress-fill-blue {
            height: 100%;
            background: #3b82f6;
            float: left;
        }
        .progress-fill-green {
            height: 100%;
            background: #22c55e;
            float: left;
        }
        .progress-fill-red {
            height: 100%;
            background: #ef4444;
            float: left;
        }
        /* Chronological trend timeline: one cell per phase, in order */
        .timeline-bar {
            width: 100%;
            height: 14px;
            border-collapse: collapse;
            table-layout: fixed;
            background: #f1f5f9;
            margin: 6px 0;
        }
        .timeline-bar td {
            height: 14px;
            padding: 0;
            line-height: 14px;
            font-size: 1px;
        }
        .timeline-losing { background: #3b82f6; }
        .timeline-stable { background: #22c55e; }
        .timeline-gaining { background: #ef4444; }
        .timeline-ends {
            width: 100%;
            font-size: 9px;
            color: #94a3b8;
        }
        .progress-labels {
            width: 100%;
            font-size: 9px;
        }
        .progress-labels td {
            padding: 2px 0;
        }

        /* Milestones */
        .milestone-list {
            width: 100%;
            border-collapse: collapse;
        }
        .milestone-list td {
            padding: 4px 8px;
            font-size: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .milestone-icon {
            width: 20px;
            text-align: center;
            color: #16a34a;
            font-weight: 700;
            font-size: 12px;
        }
        .milestone-label {
            font-weight: 600;
            color: #1e293b;
        }
        .milestone-date {
            color: #64748b;
            text-align: right;
        }
        .milestone-days {
            color: #94a3b8;
            text-align: right;
            font-size: 9px;
        }

        /* Plateau & Whoosh items */
        .event-item {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 6px;
            font-size: 10px;
        }
        .event-item .event-title {
            font-weight: 600;
            color: #334155;
        }
        .event-item .event-detail {
            color: #64748b;
            font-size: 9px;
        }
        .maintenance-stability {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 7px 9px;
            margin-bottom: 7px;
            color: #15803d;
            font-size: 10px;
            font-weight: 600;
        }

        /* Page break */
        .page-break {
            page-break-before: always;
        }

        /* Footer */
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
            font-size: 8px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    @php
        $maintenanceStatus = $maintenance['status'];
        $maintenanceBoxClass = 'maintenance-box';

        if (str_contains($maintenanceStatus, 'IN RANGE') && $maintenance['trend'] === 'stable') {
            $maintenanceBoxClass .= '';
        } elseif (str_contains($maintenanceStatus, 'IN RANGE')) {
            $maintenanceBoxClass .= ' status-drift';
        } else {
            $maintenanceBoxClass .= ' status-alert';
        }

        $currentTrendClass = $currentRate['trend'] === 'stable' ? 'card-green' : 'card-blue';
        $currentTrendTextClass = $currentRate['trend'] === 'stable' ? 'green' : 'blue';
    @endphp

    <!-- HEADER -->
    <div class="header">
        <h1>Weight Rate &amp; Maintenance Report</h1>
        <div class="date">Generated on {{ $reportDate }}</div>
    </div>

    <!-- TRANSFORMATION HERO -->
    <div class="hero">
        <div class="big-number">{{ $transformation['totalChange'] }} kg</div>
        <div class="subtitle">{{ $transformation['pctLoss'] }}% body weight lost in {{ $transformation['durationDays'] }} days</div>
        <table class="hero-stats">
            <tr>
                <td>
                    <div class="stat-label">Start</div>
                    <div class="stat-value">{{ $transformation['startWeight'] }} kg</div>
                    <div class="stat-label">BMI {{ $transformation['startBMI'] }} &middot; {{ $transformation['startDate'] }}</div>
                </td>
                <td>
                    <div class="stat-label">Current</div>
                    <div class="stat-value">{{ $transformation['endWeight'] }} kg</div>
                    <div class="stat-label">BMI {{ $transformation['endBMI'] }} &middot; {{ $transformation['endDate'] }}</div>
                </td>
                <td>
                    <div class="stat-label">Lifetime averages</div>
                    <div class="stat-value">{{ $transformation['avgWeeklyLoss'] }} kg/wk</div>
                    <div class="stat-label">{{ $transformation['avgMonthlyLoss'] }} kg/month</div>
                    <div class="stat-label" style="margin-top: 4px; color: #4338ca; font-weight: 600;">~{{ $transformation['avgDailyEnergyEquivalent'] }} kcal/day energy equivalent</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- MAINTENANCE STATUS -->
    <div class="{{ $maintenanceBoxClass }}">
        <div class="maintenance-status">{{ $maintenance['status'] }}</div>
        <div class="maintenance-subtitle">
            Target {{ $maintenance['targetWeight'] }} kg &middot; maintenance range {{ $maintenance['minWeight'] }}&ndash;{{ $maintenance['maxWeight'] }} kg
        </div>
        <table class="maintenance-table">
            <tr>
                <td>
                    <div class="label">30-day trend</div>
                    <div class="value">{{ $maintenance['weeklyTrend'] }} kg/wk</div>
                    <div class="label">{{ ucfirst($maintenance['trend']) }}</div>
                </td>
                <td>
                    <div class="label">In-range streak</div>
                    <div class="value">{{ $maintenance['streakDays'] }} days</div>
                    <div class="label">{{ $maintenance['streakEntries'] }} weigh-ins</div>
                </td>
                <td>
                    <div class="label">Last 30 days</div>
                    <div class="value">{{ $maintenance['last30InRangePct'] }}%</div>
                    <div class="label">weigh-ins in range</div>
                </td>
                <td>
                    <div class="label">Energy estimates</div>
                    <div class="value">~{{ number_format($maintenance['sedentaryTdee']) }}</div>
                    <div class="label">kcal/day sedentary TDEE</div>
                </td>
            </tr>
        </table>
        <div class="maintenance-note">
            BMR ~{{ number_format($maintenance['bmr']) }} kcal/day &middot; Mifflin-St Jeor, male, age {{ $maintenance['age'] }}, {{ $maintenance['heightCm'] }} cm. Actual maintenance may be higher with exercise and daily activity.
        </div>
    </div>

    <!-- FUN FACTS -->
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; padding: 16px 20px; margin-bottom: 20px;">
        <div style="font-size: 13px; font-weight: 700; color: #92400e; margin-bottom: 10px;">What {{ $transformation['totalChange'] }} kg Looks Like</div>
        <table style="width: 100%; font-size: 10px; color: #78350f;">
            <tr>
                <td style="width: 50%; vertical-align: top; padding-right: 12px;">
                    <div style="margin-bottom: 6px;">You removed the equivalent of:</div>
                    @foreach($funFacts['equivalents'] as $eq)
                    <div style="padding: 3px 0;">&#8226; {{ $eq }}</div>
                    @endforeach
                </td>
                <td style="width: 50%; vertical-align: top; padding-left: 12px; border-left: 1px solid #fde68a;">
                    <div style="margin-bottom: 6px;">Rough body / energy context:</div>
                    @foreach($funFacts['bodyImpact'] as $impact)
                    <div style="padding: 3px 0;">&#8226; {{ $impact }}</div>
                    @endforeach
                </td>
            </tr>
        </table>
    </div>

    <!-- CURRENT RATE + HISTORICAL SUMMARY -->
    <table class="cards">
        <tr>
            <td class="card {{ $currentTrendClass }}" style="width: 25%;">
                <div class="card-label">30-Day Trend</div>
                <div class="card-value {{ $currentTrendTextClass }}">{{ $currentRate['weekly'] }} kg/week</div>
                <div class="card-detail">{{ $currentRate['daily'] }} kg/day</div>
                <div class="card-detail">{{ ucfirst($currentRate['trend']) }} &middot; {{ $currentRate['entries'] }} entries</div>
            </td>
            <td class="card card-green" style="width: 25%;">
                <div class="card-label">Fastest Loss Rate</div>
                <div class="card-value green">{{ $historicalSummary['bestDaily'] }} kg/day</div>
                <div class="card-detail">{{ $historicalSummary['bestWeekly'] }} kg/week</div>
                <div class="card-detail">{{ $historicalSummary['bestDate'] }}</div>
            </td>
            <td class="card card-red" style="width: 25%;">
                <div class="card-label">Highest Gain Rate</div>
                <div class="card-value red">{{ $historicalSummary['worstDaily'] }} kg/day</div>
                <div class="card-detail">{{ $historicalSummary['worstWeekly'] }} kg/week</div>
                <div class="card-detail">{{ $historicalSummary['worstDate'] }}</div>
            </td>
            <td class="card card-blue" style="width: 25%;">
                <div class="card-label">Lifetime Avg Rate</div>
                <div class="card-value blue">{{ $historicalSummary['avgDaily'] }} kg/day</div>
                <div class="card-detail">{{ $historicalSummary['avgWeekly'] }} kg/week</div>
                <div class="card-detail">{{ $historicalSummary['totalWindows'] }} windows</div>
            </td>
        </tr>
    </table>

    <!-- TREND TIMELINE -->
    <div class="section-title">Trend Timeline</div>
    <table class="timeline-bar" cellpadding="0" cellspacing="0">
        <tr>
            @foreach($timeline['segments'] as $seg)
            <td class="timeline-{{ $seg['trend'] }}" style="width: {{ $seg['widthPct'] }}%;"></td>
            @endforeach
        </tr>
    </table>
    <table class="timeline-ends">
        <tr>
            <td style="width: 33%;">{{ $timeline['start'] }}</td>
            <td style="width: 34%; text-align: center;">{{ $timeline['phaseCount'] }} phases, oldest to newest</td>
            <td style="width: 33%; text-align: right;">{{ $timeline['end'] }}</td>
        </tr>
    </table>
    <table class="progress-labels">
        <tr>
            <td class="blue" style="width: 33%;">&#183; Losing {{ $distribution['losingPct'] }}% ({{ $distribution['losingCount'] }} windows)</td>
            <td class="green" style="width: 34%; text-align: center;">&#183; Stable {{ $distribution['stablePct'] }}% ({{ $distribution['stableCount'] }} windows)</td>
            <td class="red" style="width: 33%; text-align: right;">&#183; Gaining {{ $distribution['gainingPct'] }}% ({{ $distribution['gainingCount'] }} windows)</td>
        </tr>
    </table>
    @if(!empty($timeline['interruptions']))
    <div style="font-size: 10px; color: #475569; margin-top: 4px;">
        Longest interruptions:
        @foreach($timeline['interruptions'] as $break)
        <span class="{{ $break['trend'] === 'gaining' ? 'red' : 'green' }}">{{ $break['label'] }}</span>@if(!$loop->last) &middot; @endif
        @endforeach
    </div>
    @endif
    <div style="font-size: 10px; color: #475569; margin-top: 6px;">
        Current streak: <strong>{{ $streak['count'] }} consecutive windows {{ $streak['label'] }}</strong>
        &middot; Stable means within &plusmn;0.25 kg/week.
    </div>

    <!-- MILESTONES -->
    @if(!empty($milestones))
    <div class="section-title">Progress Milestones</div>
    <table class="milestone-list">
        @foreach($milestones as $m)
        <tr>
            <td class="milestone-icon">&check;</td>
            <td class="milestone-label">{{ $m['label'] }}</td>
            <td class="milestone-date">{{ $m['date'] }}</td>
            <td class="milestone-days">{{ $m['days'] }} days in</td>
        </tr>
        @endforeach
    </table>
    @endif

    <!-- VOLATILITY -->
    <div class="section-title">Weight Stability</div>
    <table class="cards">
        <tr>
            <td class="card" style="width: 33%;">
                <div class="card-label">Daily Volatility (Std Dev)</div>
                <div class="card-value slate">{{ $volatility['stdDev'] }} kg</div>
            </td>
            <td class="card card-blue" style="width: 33%;">
                <div class="card-label">Largest Single-Day Drop</div>
                <div class="card-value blue">{{ $volatility['maxDrop'] }} kg</div>
                <div class="card-detail">{{ $volatility['maxDropDate'] }}</div>
            </td>
            <td class="card card-blue" style="width: 33%;">
                <div class="card-label">Largest Single-Day Gain</div>
                <div class="card-value blue">+{{ $volatility['maxGain'] }} kg</div>
                <div class="card-detail">{{ $volatility['maxGainDate'] }}</div>
            </td>
        </tr>
    </table>

    <!-- PLATEAUS & WHOOSHES side by side -->
    <table class="cards" style="margin-top: 10px;">
        <tr>
            <td class="card" style="width: 50%; vertical-align: top;">
                <div class="card-label" style="font-size: 11px; font-weight: 700; color: #1e1b4b; margin-bottom: 8px;">Plateau / Maintenance Stability</div>
                @if(!empty($plateaus))
                <div style="margin-bottom: 6px;">
                    <span style="font-size: 10px; color: #475569;">
                        {{ count($plateaus) }} historical stable periods &middot; Longest: {{ $plateauStats['longest'] }} days &middot; Avg: {{ $plateauStats['avg'] }} days
                    </span>
                </div>

                @if($plateauStats['maintenanceStability'])
                <div class="maintenance-stability">Current stable period is inside the maintenance range &mdash; this is maintenance stability, not a problematic plateau.</div>
                @elseif($plateauStats['currentlyInPlateau'])
                <div style="background: #fff7ed; border: 1px solid #fed7aa; border-radius: 6px; padding: 7px 9px; margin-bottom: 7px; color: #c2410c; font-size: 10px; font-weight: 600;">Currently in a plateau outside the maintenance range.</div>
                @endif

                @foreach($plateaus as $p)
                <div class="event-item">
                    <span class="event-title">{{ $p['start'] }} &ndash; {{ $p['end'] }}</span>
                    <span class="event-detail">&middot; {{ $p['days'] }}d &middot; ~{{ $p['avgWeight'] }} kg</span>
                </div>
                @endforeach
                @else
                <div style="font-size: 10px; color: #64748b;">No stable periods detected</div>
                @endif
            </td>
            <td class="card" style="width: 50%; vertical-align: top;">
                <div class="card-label" style="font-size: 11px; font-weight: 700; color: #1e1b4b; margin-bottom: 8px;">Whoosh Events</div>
                @if(!empty($whooshes))
                <div style="margin-bottom: 6px;">
                    <span style="font-size: 10px; color: #475569;">
                        {{ count($whooshes) }} events &middot; Largest: {{ $whooshStats['largest'] }} kg
                    </span>
                </div>
                @foreach($whooshes as $w)
                <div class="event-item">
                    <span class="event-title">{{ $w['date'] }}</span>
                    <span class="event-detail">&middot; {{ $w['drop'] }} kg &nbsp;in {{ $w['days'] }} days</span>
                </div>
                @endforeach
                @else
                <div style="font-size: 10px; color: #64748b;">No whoosh events detected</div>
                @endif
            </td>
        </tr>
    </table>

    <!-- WEEKLY BREAKDOWN -->
    <div class="page-break"></div>
    <div class="section-title">Weekly Breakdown</div>
    <div style="font-size: 8px; color: #64748b; margin-bottom: 8px;">
        Weekly colors are maintenance-aware: green = stable / moving toward range, blue = movement while still in range, amber/red = needs attention. A single weigh-in does not infer a weekly rate.
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Week</th>
                <th>Start</th>
                <th>End</th>
                <th>Change</th>
                <th>Daily Rate</th>
                <th>Weekly Rate</th>
                <th>Entries</th>
            </tr>
        </thead>
        <tbody>
            @foreach($weeks as $week)
            <tr>
                <td>{{ $week['period'] }}</td>
                <td>{{ $week['startWeight'] }} kg</td>
                <td>{{ $week['endWeight'] }} kg</td>
                <td style="color: {{ $week['changeColor'] }};">{{ $week['change'] }} kg</td>
                <td style="color: {{ $week['changeColor'] }};">{{ $week['dailyRate'] }}{{ $week['dailyRate'] !== 'n/a' ? ' kg' : '' }}</td>
                <td style="color: {{ $week['changeColor'] }};">{{ $week['weeklyRate'] }}{{ $week['weeklyRate'] !== 'n/a' ? ' kg' : '' }}</td>
                <td>{{ $week['entries'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Weight Rate &amp; Maintenance Report &middot; Generated {{ $reportDate }} &middot; {{ $transformation['durationDays'] }} days of tracking
    </div>

</body>
</html>
