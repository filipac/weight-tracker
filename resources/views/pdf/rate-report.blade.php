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
            background: linear-gradient(135deg, #eef2ff, #e0e7ff);
            border: 1px solid #c7d2fe;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
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
        .slate { color: #64748b; }

        .card-green { background: #f0fdf4; border-color: #bbf7d0; }
        .card-red { background: #fef2f2; border-color: #fecaca; }
        .card-blue { background: #eff6ff; border-color: #bfdbfe; }
        .card-purple { background: #f5f3ff; border-color: #ddd6fe; }

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
        .progress-fill-green {
            height: 100%;
            background: #22c55e;
            float: left;
        }
        .progress-fill-gray {
            height: 100%;
            background: #cbd5e1;
            float: left;
        }
        .progress-fill-red {
            height: 100%;
            background: #ef4444;
            float: left;
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

    <!-- HEADER -->
    <div class="header">
        <h1>Weight Rate Report</h1>
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
                    <div class="stat-label">Averages</div>
                    <div class="stat-value">{{ $transformation['avgWeeklyLoss'] }} kg/wk</div>
                    <div class="stat-label">{{ $transformation['avgMonthlyLoss'] }} kg/month</div>
                    <div class="stat-label" style="margin-top: 4px; color: #4338ca; font-weight: 600;">~{{ $transformation['avgDailyDeficit'] }} kcal/day deficit</div>
                </td>
            </tr>
        </table>
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
                    <div style="margin-bottom: 6px;">Body impact:</div>
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
            <td class="card card-green" style="width: 25%;">
                <div class="card-label">Current Daily Rate</div>
                <div class="card-value green">{{ $currentRate['daily'] }} kg/day</div>
                <div class="card-detail">{{ $currentRate['weekly'] }} kg/week</div>
                <div class="card-detail">{{ $currentRate['date'] }} &middot; {{ $currentRate['entries'] }} entries</div>
            </td>
            <td class="card card-green" style="width: 25%;">
                <div class="card-label">Best Rate</div>
                <div class="card-value green">{{ $historicalSummary['bestDaily'] }} kg/day</div>
                <div class="card-detail">{{ $historicalSummary['bestWeekly'] }} kg/week</div>
                <div class="card-detail">{{ $historicalSummary['bestDate'] }}</div>
            </td>
            <td class="card card-red" style="width: 25%;">
                <div class="card-label">Worst Rate</div>
                <div class="card-value red">{{ $historicalSummary['worstDaily'] }} kg/day</div>
                <div class="card-detail">{{ $historicalSummary['worstWeekly'] }} kg/week</div>
                <div class="card-detail">{{ $historicalSummary['worstDate'] }}</div>
            </td>
            <td class="card card-blue" style="width: 25%;">
                <div class="card-label">Average Rate</div>
                <div class="card-value blue">{{ $historicalSummary['avgDaily'] }} kg/day</div>
                <div class="card-detail">{{ $historicalSummary['avgWeekly'] }} kg/week</div>
                <div class="card-detail">{{ $historicalSummary['totalWindows'] }} windows</div>
            </td>
        </tr>
    </table>

    <!-- TREND DISTRIBUTION -->
    <div class="section-title">Trend Distribution</div>
    <div class="progress-bar">
        <div class="progress-fill-green" style="width: {{ $distribution['losingPct'] }}%;"></div>
        <div class="progress-fill-gray" style="width: {{ $distribution['stablePct'] }}%;"></div>
        <div class="progress-fill-red" style="width: {{ $distribution['gainingPct'] }}%;"></div>
    </div>
    <table class="progress-labels">
        <tr>
            <td class="green" style="width: 33%;">&#9679; Losing {{ $distribution['losingPct'] }}% ({{ $distribution['losingCount'] }} windows)</td>
            <td class="slate" style="width: 34%; text-align: center;">&#9679; Stable {{ $distribution['stablePct'] }}% ({{ $distribution['stableCount'] }} windows)</td>
            <td class="red" style="width: 33%; text-align: right;">&#9679; Gaining {{ $distribution['gainingPct'] }}% ({{ $distribution['gainingCount'] }} windows)</td>
        </tr>
    </table>
    <div style="font-size: 10px; color: #475569; margin-top: 6px;">
        Current streak: <strong>{{ $streak['count'] }} consecutive windows {{ $streak['label'] }}</strong>
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
            <td class="card card-green" style="width: 33%;">
                <div class="card-label">Largest Single-Day Drop</div>
                <div class="card-value green">{{ $volatility['maxDrop'] }} kg</div>
                <div class="card-detail">{{ $volatility['maxDropDate'] }}</div>
            </td>
            <td class="card card-red" style="width: 33%;">
                <div class="card-label">Largest Single-Day Gain</div>
                <div class="card-value red">+{{ $volatility['maxGain'] }} kg</div>
                <div class="card-detail">{{ $volatility['maxGainDate'] }}</div>
            </td>
        </tr>
    </table>

    <!-- PLATEAUS & WHOOSHES side by side -->
    <table class="cards">
        <tr>
            <td class="card" style="width: 50%; vertical-align: top;">
                <div class="card-label" style="font-size: 11px; font-weight: 700; color: #1e1b4b; margin-bottom: 8px;">Plateau Analysis</div>
                @if(!empty($plateaus))
                <div style="margin-bottom: 6px;">
                    <span style="font-size: 10px; color: #475569;">
                        {{ count($plateaus) }} plateaus detected &middot; Longest: {{ $plateauStats['longest'] }} days &middot; Avg: {{ $plateauStats['avg'] }} days
                    </span>
                    @if($plateauStats['currentlyInPlateau'])
                    <br><span style="font-size: 10px; color: #dc2626; font-weight: 600;">Currently in plateau!</span>
                    @endif
                </div>
                @foreach($plateaus as $p)
                <div class="event-item">
                    <span class="event-title">{{ $p['start'] }} &ndash; {{ $p['end'] }}</span>
                    <span class="event-detail">&middot; {{ $p['days'] }}d &middot; ~{{ $p['avgWeight'] }} kg</span>
                </div>
                @endforeach
                @else
                <div style="font-size: 10px; color: #64748b;">No plateaus detected</div>
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
                <td style="color: {{ $week['changeColor'] }};">{{ $week['dailyRate'] }} kg</td>
                <td style="color: {{ $week['changeColor'] }};">{{ $week['weeklyRate'] }} kg</td>
                <td>{{ $week['entries'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Weight Rate Report &middot; Generated {{ $reportDate }} &middot; {{ $transformation['durationDays'] }} days of tracking
    </div>

</body>
</html>
