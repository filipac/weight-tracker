import { useState, useMemo, useCallback } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Activity, TrendingDown, TrendingUp, Minus, BarChart3 } from 'lucide-react'

/**
 * Compute the slope (kg/day) via simple linear regression
 * on a set of {dayOffset, weight} points.
 */
function linearRegressionSlope(points) {
    const n = points.length
    if (n < 2) return null

    let sumX = 0, sumY = 0, sumXY = 0, sumXX = 0
    for (const { dayOffset, weight } of points) {
        sumX += dayOffset
        sumY += weight
        sumXY += dayOffset * weight
        sumXX += dayOffset * dayOffset
    }

    const denom = n * sumXX - sumX * sumX
    if (Math.abs(denom) < 1e-10) return 0

    return (n * sumXY - sumX * sumY) / denom
}

/**
 * Parse "YYYY-MM-DD" to a Date at midnight UTC.
 */
function parseDate(str) {
    const [y, m, d] = str.split('-').map(Number)
    return new Date(Date.UTC(y, m - 1, d))
}

function daysBetween(a, b) {
    return Math.round((b - a) / 86400000)
}

/**
 * Classify a slope into a trend bucket (dead-band of 1g/day to avoid noise).
 */
function trendOf(slope) {
    return slope < -0.001 ? 'losing' : slope > 0.001 ? 'gaining' : 'stable'
}

function formatDate(date) {
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec']
    return `${date.getUTCDate()} ${months[date.getUTCMonth()]} ${date.getUTCFullYear()}`
}

/**
 * Compute slope and average weight for a given target date
 * using a rolling window over sorted entries.
 */
function computeRateAt(targetDate, sorted, windowDays) {
    const windowStartDate = new Date(targetDate.getTime() - windowDays * 86400000)

    const points = []
    for (const entry of sorted) {
        if (entry.date >= windowStartDate && entry.date <= targetDate) {
            points.push({
                dayOffset: daysBetween(windowStartDate, entry.date),
                weight: entry.weight,
            })
        }
    }

    const slope = linearRegressionSlope(points)
    if (slope === null) return null

    const avgWeight = points.reduce((s, p) => s + p.weight, 0) / points.length

    return { slope, pointCount: points.length, avgWeight }
}

export default function DailyRateExplorer({ chartData = [] }) {
    const WINDOW_DAYS = 30

    // Pre-process: sort by date, parse once
    const sorted = useMemo(() => {
        if (!chartData || chartData.length < 2) return []
        return chartData
            .map(d => ({ date: parseDate(d.date), weight: Number(d.weight) }))
            .filter(d => !isNaN(d.weight))
            .sort((a, b) => a.date - b.date)
    }, [chartData])

    // Build an array of unique dates that have enough surrounding data
    const dateIndex = useMemo(() => {
        if (sorted.length < 2) return []
        const firstDate = sorted[0].date
        const windowStart = new Date(firstDate.getTime() + WINDOW_DAYS * 86400000)

        const seen = new Set()
        const result = []
        for (const entry of sorted) {
            const key = entry.date.toISOString()
            if (entry.date >= windowStart && !seen.has(key)) {
                seen.add(key)
                result.push(entry.date)
            }
        }
        return result
    }, [sorted])

    // Precompute slopes for ALL dates — used for historical stats
    const allRates = useMemo(() => {
        return dateIndex.map(date => {
            const result = computeRateAt(date, sorted, WINDOW_DAYS)
            return result ? { date, ...result } : null
        }).filter(Boolean)
    }, [dateIndex, sorted])

    // Historical summary statistics
    const stats = useMemo(() => {
        if (allRates.length === 0) return null

        const slopes = allRates.map(r => r.slope)
        const avgSlope = slopes.reduce((a, b) => a + b, 0) / slopes.length

        // "Best" for weight loss = most negative slope
        // "Worst" for weight loss = most positive slope (or least negative)
        let bestIdx = 0, worstIdx = 0
        for (let i = 1; i < slopes.length; i++) {
            if (slopes[i] < slopes[bestIdx]) bestIdx = i
            if (slopes[i] > slopes[worstIdx]) worstIdx = i
        }

        // Count how many periods were losing vs gaining
        const losingCount = slopes.filter(s => s < -0.001).length
        const gainingCount = slopes.filter(s => s > 0.001).length
        const stableCount = slopes.length - losingCount - gainingCount

        // Current streak: how many consecutive dates at the end share the same trend
        let currentTrend = null
        let streakCount = 0
        for (let i = slopes.length - 1; i >= 0; i--) {
            const trend = trendOf(slopes[i])
            if (currentTrend === null) currentTrend = trend
            if (trend === currentTrend) {
                streakCount++
            } else {
                break
            }
        }

        // Chronological run-length encoding: merge consecutive windows sharing a
        // trend into one segment. Each window is weighted by the calendar days it
        // covers (gap until the next window) so a long phase with sparse weigh-ins
        // isn't squeezed into a sliver next to a dense one.
        const segments = []
        for (let i = 0; i < allRates.length; i++) {
            const trend = trendOf(allRates[i].slope)
            const next = allRates[i + 1]
            const spanDays = next ? Math.max(1, daysBetween(allRates[i].date, next.date)) : 1
            const last = segments[segments.length - 1]

            if (last && last.trend === trend) {
                last.endDate = allRates[i].date
                last.windows++
                last.days += spanDays
                last.slopeSum += allRates[i].slope
            } else {
                segments.push({
                    trend,
                    startDate: allRates[i].date,
                    endDate: allRates[i].date,
                    windows: 1,
                    days: spanDays,
                    slopeSum: allRates[i].slope,
                })
            }
        }

        const totalDays = segments.reduce((sum, seg) => sum + seg.days, 0)

        return {
            segments,
            totalDays,
            timelineStart: allRates[0].date,
            timelineEnd: allRates[allRates.length - 1].date,
            avgSlope,
            bestSlope: slopes[bestIdx],
            bestDate: allRates[bestIdx].date,
            worstSlope: slopes[worstIdx],
            worstDate: allRates[worstIdx].date,
            losingCount,
            gainingCount,
            stableCount,
            totalPeriods: slopes.length,
            losingPct: Math.round((losingCount / slopes.length) * 100),
            currentTrend,
            streakCount,
        }
    }, [allRates])

    const maxSlider = dateIndex.length - 1
    const [sliderPos, setSliderPos] = useState(maxSlider >= 0 ? maxSlider : 0)

    // Compute the daily rate at the selected date
    const rateData = useMemo(() => {
        if (dateIndex.length === 0 || sliderPos > maxSlider) return null

        const targetDate = dateIndex[Math.min(sliderPos, maxSlider)]
        const result = computeRateAt(targetDate, sorted, WINDOW_DAYS)
        if (!result) return null

        return {
            date: targetDate,
            slope: result.slope,
            dailyRate: Math.abs(result.slope),
            weeklyRate: Math.abs(result.slope * 7),
            trend: trendOf(result.slope),
            pointCount: result.pointCount,
            avgWeight: result.avgWeight,
        }
    }, [dateIndex, sliderPos, maxSlider, sorted])

    const handleSliderChange = useCallback((e) => {
        setSliderPos(Number(e.target.value))
    }, [])

    if (sorted.length < 2 || dateIndex.length === 0) {
        return (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Activity className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                        Daily Rate Explorer
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-sm text-slate-600 dark:text-slate-300">
                        Need at least 30 days of data to explore historical rates.
                    </p>
                </CardContent>
            </Card>
        )
    }

    const TrendIcon = rateData?.trend === 'losing' ? TrendingDown
        : rateData?.trend === 'gaining' ? TrendingUp
        : Minus

    const trendColor = rateData?.trend === 'losing'
        ? 'text-green-600 dark:text-green-400'
        : rateData?.trend === 'gaining'
            ? 'text-red-600 dark:text-red-400'
            : 'text-slate-500 dark:text-slate-400'

    const trendBg = rateData?.trend === 'losing'
        ? 'bg-green-50 dark:bg-green-950/30'
        : rateData?.trend === 'gaining'
            ? 'bg-red-50 dark:bg-red-950/30'
            : 'bg-slate-50 dark:bg-slate-900/50'

    const trendLabel = rateData?.trend === 'losing' ? 'Losing'
        : rateData?.trend === 'gaining' ? 'Gaining'
        : 'Stable'

    const formatSlope = (slope) => {
        const sign = slope < -0.001 ? '-' : slope > 0.001 ? '+' : ''
        return `${sign}${Math.abs(slope).toFixed(3)}`
    }

    const slopeColor = (slope) => {
        if (slope < -0.001) return 'text-green-600 dark:text-green-400'
        if (slope > 0.001) return 'text-red-600 dark:text-red-400'
        return 'text-slate-500 dark:text-slate-400'
    }

    const segmentColor = (trend) => trend === 'losing'
        ? 'bg-green-500 dark:bg-green-400'
        : trend === 'gaining'
            ? 'bg-red-500 dark:bg-red-400'
            : 'bg-slate-300 dark:bg-slate-600'

    const segmentLabel = (seg) => {
        const name = seg.trend === 'losing' ? 'Losing' : seg.trend === 'gaining' ? 'Gaining' : 'Stable'
        return `${name} ${seg.days} day${seg.days !== 1 ? 's' : ''}`
    }

    // Where the slider's date falls along the chronological bar
    const markerPct = stats && rateData
        ? Math.min(100, Math.max(0, (daysBetween(stats.timelineStart, rateData.date) / stats.totalDays) * 100))
        : 0

    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Activity className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                    Daily Rate Explorer
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {/* Date display */}
                <div className="text-center">
                    <span className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                        {rateData ? formatDate(rateData.date) : '—'}
                    </span>
                    <div className="text-xs text-slate-500 dark:text-slate-400">
                        30-day rolling window ({rateData?.pointCount || 0} entries)
                    </div>
                </div>

                {/* Slider */}
                <div className="px-1">
                    <input
                        type="range"
                        min={0}
                        max={maxSlider}
                        value={sliderPos}
                        onChange={handleSliderChange}
                        className="w-full accent-purple-600 dark:accent-purple-400"
                    />
                    <div className="flex justify-between text-xs text-slate-400 dark:text-slate-500 mt-1">
                        <span>{formatDate(dateIndex[0])}</span>
                        <span>{formatDate(dateIndex[maxSlider])}</span>
                    </div>
                </div>

                {/* Rate display */}
                {rateData && (
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        {/* Daily rate */}
                        <div className={`rounded-lg p-3 ${trendBg}`}>
                            <div className="text-xs text-slate-500 dark:text-slate-400 mb-1">Daily Rate</div>
                            <div className="flex items-center gap-1.5">
                                <TrendIcon className={`h-4 w-4 ${trendColor}`} />
                                <span className={`text-xl font-bold ${trendColor}`}>
                                    {rateData.trend === 'losing' ? '-' : rateData.trend === 'gaining' ? '+' : ''}{rateData.dailyRate.toFixed(3)} kg
                                </span>
                            </div>
                            <div className={`text-xs mt-0.5 ${trendColor}`}>{trendLabel}</div>
                        </div>

                        {/* Weekly rate */}
                        <div className={`rounded-lg p-3 ${trendBg}`}>
                            <div className="text-xs text-slate-500 dark:text-slate-400 mb-1">Weekly Rate</div>
                            <div className={`text-xl font-bold ${trendColor}`}>
                                {rateData.trend === 'losing' ? '-' : rateData.trend === 'gaining' ? '+' : ''}{rateData.weeklyRate.toFixed(2)} kg
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">per week</div>
                        </div>

                        {/* Avg weight in window */}
                        <div className="rounded-lg bg-blue-50 p-3 dark:bg-blue-950/30">
                            <div className="text-xs text-slate-500 dark:text-slate-400 mb-1">Avg Weight</div>
                            <div className="text-xl font-bold text-slate-900 dark:text-slate-100">
                                {rateData.avgWeight.toFixed(1)} kg
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">in window</div>
                        </div>
                    </div>
                )}

                {/* Historical Stats Section */}
                {stats && (
                    <div className="border-t border-slate-200 pt-4 dark:border-slate-800">
                        <div className="flex items-center gap-2 mb-3">
                            <BarChart3 className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                            <span className="text-sm font-semibold text-slate-700 dark:text-slate-300">Historical Summary</span>
                        </div>

                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            {/* Best (fastest loss) */}
                            <div className="rounded-lg border border-green-200 bg-green-50/50 p-3 dark:border-green-800 dark:bg-green-950/20">
                                <div className="text-xs text-slate-500 dark:text-slate-400 mb-1">Best Daily Rate</div>
                                <div className={`text-lg font-bold ${slopeColor(stats.bestSlope)}`}>
                                    {formatSlope(stats.bestSlope)} kg/day
                                </div>
                                <div className={`text-xs font-medium ${slopeColor(stats.bestSlope)}`}>
                                    {formatSlope(stats.bestSlope * 7)} kg/week
                                </div>
                                <div className="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                    {formatDate(stats.bestDate)}
                                </div>
                            </div>

                            {/* Worst (most gaining or least losing) */}
                            <div className="rounded-lg border border-red-200 bg-red-50/50 p-3 dark:border-red-800 dark:bg-red-950/20">
                                <div className="text-xs text-slate-500 dark:text-slate-400 mb-1">Worst Daily Rate</div>
                                <div className={`text-lg font-bold ${slopeColor(stats.worstSlope)}`}>
                                    {formatSlope(stats.worstSlope)} kg/day
                                </div>
                                <div className={`text-xs font-medium ${slopeColor(stats.worstSlope)}`}>
                                    {formatSlope(stats.worstSlope * 7)} kg/week
                                </div>
                                <div className="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                    {formatDate(stats.worstDate)}
                                </div>
                            </div>

                            {/* Average */}
                            <div className="rounded-lg border border-blue-200 bg-blue-50/50 p-3 dark:border-blue-800 dark:bg-blue-950/20">
                                <div className="text-xs text-slate-500 dark:text-slate-400 mb-1">Average Daily Rate</div>
                                <div className={`text-lg font-bold ${slopeColor(stats.avgSlope)}`}>
                                    {formatSlope(stats.avgSlope)} kg/day
                                </div>
                                <div className={`text-xs font-medium ${slopeColor(stats.avgSlope)}`}>
                                    {formatSlope(stats.avgSlope * 7)} kg/week
                                </div>
                                <div className="text-xs text-slate-400 dark:text-slate-500 mt-1">
                                    across all {stats.totalPeriods} windows
                                </div>
                            </div>
                        </div>

                        {/* Trend distribution bar + streak */}
                        <div className="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            {/* Distribution */}
                            <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-3 dark:border-slate-700 dark:bg-slate-900/30">
                                <div className="mb-2 flex items-baseline justify-between">
                                    <span className="text-xs text-slate-500 dark:text-slate-400">Trend Timeline</span>
                                    <span className="text-[10px] text-slate-400 dark:text-slate-500">oldest → newest</span>
                                </div>
                                {/* Chronological bar: one slice per trend phase, in order */}
                                <div className="relative h-3 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
                                    <div className="flex h-full w-full">
                                        {stats.segments.map((seg, i) => (
                                            <div
                                                key={i}
                                                title={`${segmentLabel(seg)}: ${formatDate(seg.startDate)} → ${formatDate(seg.endDate)} · ${formatSlope(seg.slopeSum / seg.windows)} kg/day`}
                                                className={segmentColor(seg.trend)}
                                                style={{
                                                    flex: `0 1 ${(seg.days / stats.totalDays) * 100}%`,
                                                    minWidth: '2px',
                                                }}
                                            />
                                        ))}
                                    </div>
                                    {rateData && (
                                        <div
                                            className="pointer-events-none absolute top-0 h-full w-0.5 -translate-x-1/2 bg-slate-900/70 dark:bg-white/80"
                                            style={{ left: `${markerPct}%` }}
                                            title={formatDate(rateData.date)}
                                        />
                                    )}
                                </div>
                                <div className="mt-1 flex justify-between text-[10px] text-slate-400 dark:text-slate-500">
                                    <span>{formatDate(stats.timelineStart)}</span>
                                    <span>{stats.segments.length} phase{stats.segments.length !== 1 ? 's' : ''}</span>
                                    <span>{formatDate(stats.timelineEnd)}</span>
                                </div>
                                <div className="flex justify-between text-xs mt-1.5">
                                    <span className="text-green-600 dark:text-green-400">
                                        Losing {stats.losingPct}%
                                    </span>
                                    {stats.stableCount > 0 && (
                                        <span className="text-slate-500 dark:text-slate-400">
                                            Stable {Math.round((stats.stableCount / stats.totalPeriods) * 100)}%
                                        </span>
                                    )}
                                    <span className="text-red-600 dark:text-red-400">
                                        Gaining {Math.round((stats.gainingCount / stats.totalPeriods) * 100)}%
                                    </span>
                                </div>
                            </div>

                            {/* Current streak */}
                            <div className="rounded-lg border border-slate-200 bg-slate-50/50 p-3 dark:border-slate-700 dark:bg-slate-900/30">
                                <div className="text-xs text-slate-500 dark:text-slate-400 mb-1">Current Trend Streak</div>
                                <div className={`text-lg font-bold ${
                                    stats.currentTrend === 'losing' ? 'text-green-600 dark:text-green-400'
                                    : stats.currentTrend === 'gaining' ? 'text-red-600 dark:text-red-400'
                                    : 'text-slate-500 dark:text-slate-400'
                                }`}>
                                    {stats.streakCount} consecutive window{stats.streakCount !== 1 ? 's' : ''}
                                </div>
                                <div className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    {stats.currentTrend === 'losing' ? 'Consistently losing weight'
                                        : stats.currentTrend === 'gaining' ? 'Consistently gaining weight'
                                        : 'Weight has been stable'}
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    )
}
