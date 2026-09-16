import { useState, useEffect, useMemo } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Ruler, History } from 'lucide-react'

export default function WHtRVisualization({ waistCm, waistChartData = [] }) {
    // State for historical navigation
    const [selectedIndex, setSelectedIndex] = useState(null)
    const [isHistoricalView, setIsHistoricalView] = useState(false)
    const [isFutureView, setIsFutureView] = useState(false)

    // Height hardcoded to 173cm
    const heightCm = 173

    // Healthy waist target (WHtR = 0.5)
    const healthyWaistTarget = heightCm * 0.5

    // Calculate WHtR from waist measurement
    const calculateWHtR = (waist) => {
        return waist / heightCm
    }

    // Calculate linear regression for waist trend
    const calculateWaistTrend = useMemo(() => {
        if (waistChartData.length < 2) return null

        const firstDate = new Date(waistChartData[0].date)
        const days = []
        const waists = []

        waistChartData.forEach(entry => {
            const daysSinceStart = Math.floor((new Date(entry.date) - firstDate) / (24 * 60 * 60 * 1000))
            days.push(daysSinceStart)
            waists.push(parseFloat(entry.waist))
        })

        // Simple linear regression
        const n = days.length
        const sumX = days.reduce((a, b) => a + b, 0)
        const sumY = waists.reduce((a, b) => a + b, 0)
        const sumXY = days.reduce((acc, x, i) => acc + x * waists[i], 0)
        const sumXX = days.reduce((acc, x) => acc + x * x, 0)

        const slope = (n * sumXY - sumX * sumY) / (n * sumXX - sumX * sumX)

        return {
            dailyChange: slope,
            trend: slope < 0 ? 'decreasing' : 'increasing'
        }
    }, [waistChartData])

    // Generate combined data with future predictions
    const combinedData = useMemo(() => {
        if (!waistChartData.length) return []

        // Start with historical data
        const historicalData = waistChartData.map(entry => ({
            ...entry,
            type: 'historical'
        }))

        // Check if we have trend data and waist is above healthy target
        if (!calculateWaistTrend || calculateWaistTrend.dailyChange >= 0) {
            return historicalData
        }

        const latestEntry = waistChartData[waistChartData.length - 1]
        const latestDate = new Date(latestEntry.date)
        const latestWaist = parseFloat(latestEntry.waist)

        // Only predict if current waist is above healthy target
        if (latestWaist <= healthyWaistTarget) {
            return historicalData
        }

        const dailyChange = calculateWaistTrend.dailyChange
        const waistToLose = latestWaist - healthyWaistTarget
        const daysToGoal = Math.ceil(Math.abs(waistToLose / dailyChange))

        // Generate daily predictions
        const futureData = []
        const msPerDay = 24 * 60 * 60 * 1000

        for (let day = 1; day <= daysToGoal; day++) {
            const futureDate = new Date(latestDate.getTime() + day * msPerDay)
            const predictedWaist = latestWaist + (dailyChange * day)
            const finalWaist = Math.max(predictedWaist, healthyWaistTarget)

            futureData.push({
                date: futureDate.toISOString().split('T')[0],
                waist: Math.round(finalWaist * 10) / 10,
                type: 'predicted'
            })
        }

        return [...historicalData, ...futureData]
    }, [waistChartData, calculateWaistTrend, healthyWaistTarget])

    // Find the index of the last historical entry
    const lastHistoricalIndex = useMemo(() => {
        return combinedData.findIndex((entry, index) => {
            const nextEntry = combinedData[index + 1]
            return nextEntry && nextEntry.type === 'predicted'
        })
    }, [combinedData])

    // The "current" index is the last historical entry
    const currentIndex = lastHistoricalIndex >= 0 ? lastHistoricalIndex : waistChartData.length - 1

    // Initialize to current (latest actual entry) when combinedData is available
    useEffect(() => {
        if (combinedData.length > 0 && selectedIndex === null) {
            const historicalEndIndex = combinedData.findIndex(e => e.type === 'predicted') - 1
            setSelectedIndex(historicalEndIndex >= 0 ? historicalEndIndex : combinedData.length - 1)
        }
    }, [combinedData])

    // Get the selected waist entry from combined data
    const selectedEntry = combinedData.length > 0 && selectedIndex !== null
        ? combinedData[selectedIndex]
        : null

    // Determine if we're in historical view, current view, or future prediction view
    const isPredictedEntry = selectedEntry?.type === 'predicted'
    const isCurrentEntry = selectedIndex === currentIndex

    // Use the selected data for display
    const displayWaist = selectedEntry
        ? parseFloat(selectedEntry.waist)
        : parseFloat(waistCm)
    const displayWHtR = selectedEntry
        ? calculateWHtR(selectedEntry.waist)
        : (waistCm ? waistCm / heightCm : 0)
    const displayDate = selectedEntry
        ? isPredictedEntry
            ? `${new Date(selectedEntry.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })} (Predicted)`
            : isCurrentEntry
                ? 'Current'
                : new Date(selectedEntry.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
        : 'Current'

    if (!waistCm || waistCm <= 0) {
        return null
    }

    // Use displayWHtR instead of whtr
    const whtr = displayWHtR

    // WHtR ranges with colors
    const ranges = [
        { label: 'Healthy', min: 0, max: 0.5, color: 'bg-green-200 dark:bg-green-900', description: 'Lower risk' },
        { label: 'Increased Risk', min: 0.5, max: 0.6, color: 'bg-yellow-200 dark:bg-yellow-900', description: 'Moderate risk' },
        { label: 'High Risk', min: 0.6, max: 0.7, color: 'bg-orange-200 dark:bg-orange-900', description: 'High risk' },
        { label: 'Very High Risk', min: 0.7, max: 1.0, color: 'bg-red-200 dark:bg-red-900', description: 'Very high risk' }
    ]

    // Calculate total range for visualization (0-1.0 WHtR)
    const minWHtR = 0.3
    const maxWHtR = 0.8

    // Calculate position as percentage
    const getPosition = (whtrValue) => {
        const clampedValue = Math.max(minWHtR, Math.min(maxWHtR, whtrValue))
        return ((clampedValue - minWHtR) / (maxWHtR - minWHtR)) * 100
    }

    // Get category name for a WHtR value
    const getCategory = (whtrValue) => {
        const range = ranges.find(r => whtrValue >= r.min && whtrValue < r.max)
        return range ? range.label : 'Out of range'
    }

    // Get category color
    const getCategoryColor = (whtrValue) => {
        const range = ranges.find(r => whtrValue >= r.min && whtrValue < r.max)
        if (!range) return 'text-gray-600 dark:text-gray-400'

        if (range.label === 'Healthy') return 'text-green-600 dark:text-green-400'
        if (range.label === 'Increased Risk') return 'text-yellow-600 dark:text-yellow-400'
        if (range.label === 'High Risk') return 'text-orange-600 dark:text-orange-400'
        if (range.label === 'Very High Risk') return 'text-red-600 dark:text-red-400'
        return 'text-gray-600 dark:text-gray-400'
    }

    const currentPosition = getPosition(whtr)

    // Calculate healthy waist circumference (WHtR < 0.5)
    const healthyWaist = heightCm * 0.5
    const waistToHealthy = displayWaist - healthyWaist

    return (
        <>
            <style>{`
                input[type="range"].slider-thumb {
                    -webkit-appearance: none;
                    appearance: none;
                }

                input[type="range"].slider-thumb::-webkit-slider-thumb {
                    -webkit-appearance: none;
                    appearance: none;
                    width: 18px;
                    height: 18px;
                    border-radius: 50%;
                    background: rgb(147 51 234);
                    cursor: pointer;
                    border: 2px solid white;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                    transition: all 0.15s ease;
                }

                input[type="range"].slider-thumb::-webkit-slider-thumb:hover {
                    transform: scale(1.2);
                    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
                }

                input[type="range"].slider-thumb::-moz-range-thumb {
                    width: 18px;
                    height: 18px;
                    border-radius: 50%;
                    background: rgb(147 51 234);
                    cursor: pointer;
                    border: 2px solid white;
                    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
                    transition: all 0.15s ease;
                }

                input[type="range"].slider-thumb::-moz-range-thumb:hover {
                    transform: scale(1.2);
                    box-shadow: 0 3px 6px rgba(0, 0, 0, 0.3);
                }

                input[type="range"].slider-thumb::-webkit-slider-runnable-track {
                    height: 8px;
                    border-radius: 4px;
                }

                input[type="range"].slider-thumb::-moz-range-track {
                    height: 8px;
                    border-radius: 4px;
                }
            `}</style>
            <Card className="flex-shrink-0">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Ruler className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                        Waist-to-Height Ratio (WHtR)
                    </CardTitle>
                </CardHeader>
                <CardContent>
                {/* Current WHtR Info */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div className={`rounded-lg p-4 ${isPredictedEntry ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-purple-50 dark:bg-purple-900/20'}`}>
                        <div className="flex items-center gap-2 mb-2">
                            <Ruler className={`h-4 w-4 ${isPredictedEntry ? 'text-emerald-600 dark:text-emerald-400' : 'text-purple-600 dark:text-purple-400'}`} />
                            <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {isPredictedEntry ? 'Predicted WHtR' : isHistoricalView ? 'Historical WHtR' : 'Current WHtR'}
                            </span>
                            {isPredictedEntry && (
                                <span className="text-xs px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-800 text-emerald-700 dark:text-emerald-300">
                                    Future
                                </span>
                            )}
                        </div>
                        <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {whtr.toFixed(3)}
                        </div>
                        <div className={`text-sm font-medium mt-1 ${getCategoryColor(whtr)}`}>
                            {getCategory(whtr)}
                        </div>
                        <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {displayDate}
                        </div>
                    </div>

                    <div className="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
                        <div className="flex items-center gap-2 mb-2">
                            <Ruler className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {isPredictedEntry ? 'Predicted Measurements' : 'Measurements'}
                            </span>
                        </div>
                        <div className="text-sm text-gray-700 dark:text-gray-300 space-y-1">
                            <div>
                                <span className="font-medium">Waist:</span> {displayWaist.toFixed(1)} cm
                            </div>
                            <div>
                                <span className="font-medium">Height:</span> {heightCm} cm
                            </div>
                            {calculateWaistTrend && (
                                <div className={`text-xs mt-2 ${calculateWaistTrend.dailyChange < 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                    Trend: {calculateWaistTrend.dailyChange < 0 ? '' : '+'}{(calculateWaistTrend.dailyChange * 7).toFixed(2)} cm/week
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* Waist Timeline Navigator (Historical + Future Predictions) */}
                {combinedData.length > 1 && (
                    <div className={`mt-6 p-4 border rounded-lg ${isPredictedEntry ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800' : 'bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800'}`}>
                        <div className="flex items-center gap-2 mb-3">
                            <History className={`h-4 w-4 ${isPredictedEntry ? 'text-emerald-600 dark:text-emerald-400' : 'text-purple-600 dark:text-purple-400'}`} />
                            <span className={`text-sm font-medium ${isPredictedEntry ? 'text-emerald-800 dark:text-emerald-300' : 'text-purple-800 dark:text-purple-300'}`}>
                                Waist Timeline Navigator
                            </span>
                            {combinedData.some(e => e.type === 'predicted') && (
                                <span className="text-xs px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-800 text-emerald-700 dark:text-emerald-300">
                                    Includes predictions
                                </span>
                            )}
                        </div>

                        <div className="space-y-3">
                            <div className="flex items-center justify-between text-sm text-gray-600 dark:text-gray-400">
                                <span>{new Date(combinedData[0].date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}</span>
                                <span className={`font-semibold ${isPredictedEntry ? 'text-emerald-600 dark:text-emerald-400' : 'text-purple-600 dark:text-purple-400'}`}>
                                    {displayDate}
                                </span>
                                <span className={combinedData[combinedData.length - 1].type === 'predicted' ? 'text-emerald-600 dark:text-emerald-400' : ''}>
                                    {new Date(combinedData[combinedData.length - 1].date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })}
                                    {combinedData[combinedData.length - 1].type === 'predicted' && ' (Goal)'}
                                </span>
                            </div>

                            {/* Slider track with visual distinction between historical and predicted */}
                            <div className="relative">
                                {(() => {
                                    const historicalCount = combinedData.filter(e => e.type === 'historical').length
                                    const boundaryPercent = (historicalCount / combinedData.length) * 100

                                    return (
                                        <>
                                            <input
                                                type="range"
                                                min="0"
                                                max={combinedData.length - 1}
                                                value={selectedIndex ?? currentIndex}
                                                onChange={(e) => {
                                                    const index = parseInt(e.target.value)
                                                    setSelectedIndex(index)
                                                    setIsHistoricalView(index < currentIndex)
                                                    setIsFutureView(index > currentIndex)
                                                }}
                                                className="w-full h-2 rounded-lg appearance-none cursor-pointer slider-thumb"
                                                style={{
                                                    background: `linear-gradient(to right,
                                                        rgb(147 51 234) 0%,
                                                        rgb(147 51 234) ${((selectedIndex ?? currentIndex) / (combinedData.length - 1)) * 100}%,
                                                        ${isPredictedEntry ? 'rgb(167 243 208)' : 'rgb(233 213 255)'} ${((selectedIndex ?? currentIndex) / (combinedData.length - 1)) * 100}%,
                                                        ${boundaryPercent < 100 ? `rgb(233 213 255) ${boundaryPercent}%, rgb(167 243 208) ${boundaryPercent}%,` : ''}
                                                        ${boundaryPercent < 100 ? 'rgb(167 243 208)' : 'rgb(233 213 255)'} 100%)`
                                                }}
                                            />
                                            {/* Visual marker for "current" position */}
                                            <div
                                                className="absolute top-1/2 -translate-y-1/2 w-1 h-4 bg-blue-500 dark:bg-blue-400 rounded pointer-events-none"
                                                style={{ left: `calc(${(currentIndex / (combinedData.length - 1)) * 100}% - 2px)` }}
                                                title="Current"
                                            />
                                        </>
                                    )
                                })()}
                            </div>

                            {/* Back to Current button - prominent when in prediction mode */}
                            {isPredictedEntry && (
                                <div className="flex justify-center">
                                    <button
                                        onClick={() => {
                                            setSelectedIndex(currentIndex)
                                            setIsHistoricalView(false)
                                            setIsFutureView(false)
                                        }}
                                        className="px-4 py-2 text-sm font-medium bg-blue-500 hover:bg-blue-600 text-white rounded-lg shadow-sm transition-colors flex items-center gap-2"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" className="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                            <path fillRule="evenodd" d="M9.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L7.414 9H15a1 1 0 110 2H7.414l2.293 2.293a1 1 0 010 1.414z" clipRule="evenodd" />
                                        </svg>
                                        Back to Current
                                    </button>
                                </div>
                            )}

                            {/* Legend for slider colors */}
                            {combinedData.some(e => e.type === 'predicted') && (
                                <div className="flex items-center justify-center gap-4 text-xs">
                                    <div className="flex items-center gap-1">
                                        <div className="w-3 h-2 rounded bg-purple-200 dark:bg-purple-700"></div>
                                        <span className="text-gray-500 dark:text-gray-400">Historical</span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <div className="w-1 h-3 rounded bg-blue-500 dark:bg-blue-400"></div>
                                        <span className="text-gray-500 dark:text-gray-400">Current</span>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <div className="w-3 h-2 rounded bg-emerald-200 dark:bg-emerald-700"></div>
                                        <span className="text-gray-500 dark:text-gray-400">Predicted</span>
                                    </div>
                                </div>
                            )}

                            <div className="flex items-center justify-between gap-2">
                                <button
                                    onClick={() => {
                                        const newIndex = Math.max(0, (selectedIndex ?? currentIndex) - 1)
                                        setSelectedIndex(newIndex)
                                        setIsHistoricalView(newIndex < currentIndex)
                                        setIsFutureView(newIndex > currentIndex)
                                    }}
                                    disabled={selectedIndex === 0}
                                    className={`px-3 py-1 text-xs font-medium rounded disabled:opacity-50 disabled:cursor-not-allowed ${isPredictedEntry ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-800' : 'bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 hover:bg-purple-200 dark:hover:bg-purple-800'}`}
                                >
                                    ← Earlier
                                </button>

                                <div className="text-center flex-1">
                                    <div className="text-xs text-gray-500 dark:text-gray-400">
                                        {isPredictedEntry ? (
                                            <>Prediction {(selectedIndex ?? currentIndex) - currentIndex} of {combinedData.length - currentIndex - 1}</>
                                        ) : (
                                            <>Entry {(selectedIndex ?? currentIndex) + 1} of {currentIndex + 1}</>
                                        )}
                                    </div>
                                    {isHistoricalView && !isPredictedEntry && (
                                        <div className="text-xs font-medium text-purple-600 dark:text-purple-400 mt-1">
                                            Historical View
                                        </div>
                                    )}
                                    {isPredictedEntry && (
                                        <div className="text-xs font-medium text-emerald-600 dark:text-emerald-400 mt-1">
                                            Future Prediction
                                        </div>
                                    )}
                                    {isCurrentEntry && (
                                        <div className="text-xs font-medium text-blue-600 dark:text-blue-400 mt-1">
                                            Current Measurement
                                        </div>
                                    )}
                                </div>

                                <button
                                    onClick={() => {
                                        const newIndex = Math.min(combinedData.length - 1, (selectedIndex ?? currentIndex) + 1)
                                        setSelectedIndex(newIndex)
                                        setIsHistoricalView(newIndex < currentIndex)
                                        setIsFutureView(newIndex > currentIndex)
                                    }}
                                    disabled={selectedIndex === combinedData.length - 1}
                                    className={`px-3 py-1 text-xs font-medium rounded disabled:opacity-50 disabled:cursor-not-allowed ${isPredictedEntry || (selectedIndex ?? currentIndex) >= currentIndex ? 'bg-emerald-100 dark:bg-emerald-900 text-emerald-700 dark:text-emerald-300 hover:bg-emerald-200 dark:hover:bg-emerald-800' : 'bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 hover:bg-purple-200 dark:hover:bg-purple-800'}`}
                                >
                                    Later →
                                </button>
                            </div>

                            {/* Jump to buttons */}
                            <div className="flex items-center justify-center gap-2 pt-2 border-t border-gray-200 dark:border-gray-700">
                                <button
                                    onClick={() => {
                                        setSelectedIndex(0)
                                        setIsHistoricalView(true)
                                        setIsFutureView(false)
                                    }}
                                    className="px-2 py-1 text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 rounded hover:bg-gray-200 dark:hover:bg-gray-700"
                                >
                                    Start
                                </button>
                                <button
                                    onClick={() => {
                                        setSelectedIndex(currentIndex)
                                        setIsHistoricalView(false)
                                        setIsFutureView(false)
                                    }}
                                    className="px-2 py-1 text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-600 dark:text-blue-400 rounded hover:bg-blue-200 dark:hover:bg-blue-800"
                                >
                                    Current
                                </button>
                                {combinedData.some(e => e.type === 'predicted') && (
                                    <button
                                        onClick={() => {
                                            setSelectedIndex(combinedData.length - 1)
                                            setIsHistoricalView(false)
                                            setIsFutureView(true)
                                        }}
                                        className="px-2 py-1 text-xs font-medium bg-emerald-100 dark:bg-emerald-900 text-emerald-600 dark:text-emerald-400 rounded hover:bg-emerald-200 dark:hover:bg-emerald-800"
                                    >
                                        Goal
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>
                )}

                {/* WHtR Range Visualization */}
                <div className="space-y-4">
                    <div className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                        WHtR Risk Categories
                    </div>

                    {/* Visual Bar */}
                    <div className="relative h-16 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                        {/* Range segments */}
                        <div className="absolute inset-0 flex">
                            {ranges.map((range, index) => {
                                // Only show ranges within our visible window
                                const visibleMin = Math.max(range.min, minWHtR)
                                const visibleMax = Math.min(range.max, maxWHtR)

                                if (visibleMax <= visibleMin) return null

                                const width = ((visibleMax - visibleMin) / (maxWHtR - minWHtR)) * 100
                                return (
                                    <div
                                        key={index}
                                        className={`${range.color} flex items-center justify-center text-xs font-medium text-gray-700 dark:text-gray-300 px-1`}
                                        style={{ width: `${width}%` }}
                                        title={range.description}
                                    >
                                        <span className="truncate">{range.label}</span>
                                    </div>
                                )
                            })}
                        </div>

                        {/* Current/Historical/Predicted WHtR Marker */}
                        <div
                            className={`absolute top-0 bottom-0 w-1 z-10 transition-all duration-300 ${isPredictedEntry ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-purple-600 dark:bg-purple-400'}`}
                            style={{ left: `${currentPosition}%` }}
                        >
                            <div className="absolute -top-6 left-1/2 -translate-x-1/2 whitespace-nowrap">
                                <div className="flex items-center gap-1">
                                    <Ruler className={`h-3 w-3 ${isPredictedEntry ? 'text-emerald-600 dark:text-emerald-400' : 'text-purple-600 dark:text-purple-400'}`} />
                                    <span className={`text-xs font-semibold ${isPredictedEntry ? 'text-emerald-600 dark:text-emerald-400' : 'text-purple-600 dark:text-purple-400'}`}>
                                        {isPredictedEntry ? 'Predicted: ' : isHistoricalView ? 'Historical: ' : ''}{whtr.toFixed(3)}
                                    </span>
                                </div>
                            </div>
                            <div className={`absolute -top-2 left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent ${isPredictedEntry ? 'border-t-emerald-600 dark:border-t-emerald-400' : 'border-t-purple-600 dark:border-t-purple-400'}`}></div>
                        </div>

                        {/* Healthy Threshold Marker at 0.5 */}
                        <div
                            className="absolute top-0 bottom-0 w-0.5 bg-green-600 dark:bg-green-400 z-10 opacity-50"
                            style={{ left: `${getPosition(0.5)}%` }}
                        >
                            <div className="absolute -bottom-6 left-1/2 -translate-x-1/2 whitespace-nowrap">
                                <span className="text-xs font-semibold text-green-600 dark:text-green-400">
                                    0.5 (Threshold)
                                </span>
                            </div>
                        </div>
                    </div>

                    {/* Legend */}
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-2 mt-8">
                        {ranges.map((range, index) => (
                            <div key={index} className="flex items-center gap-2">
                                <div className={`w-4 h-4 rounded ${range.color}`}></div>
                                <span className="text-xs text-gray-600 dark:text-gray-400">
                                    {range.label}
                                </span>
                            </div>
                        ))}
                    </div>

                    {/* Health Information */}
                    <div className={`mt-4 p-4 rounded-lg border ${
                        whtr < 0.5
                            ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
                            : 'bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800'
                    }`}>
                        <div className={`font-medium mb-2 ${
                            whtr < 0.5
                                ? 'text-green-800 dark:text-green-300'
                                : 'text-orange-800 dark:text-orange-300'
                        }`}>
                            {whtr < 0.5 ? 'Healthy Range ✓' : 'Health Risk Alert'}
                        </div>

                        {whtr < 0.5 ? (
                            <div className="text-sm text-gray-700 dark:text-gray-300">
                                <p className="mb-2">
                                    Your waist-to-height ratio is in the healthy range. This is associated with:
                                </p>
                                <ul className="list-disc list-inside space-y-1 text-xs">
                                    <li>Lower risk of cardiovascular disease</li>
                                    <li>Reduced risk of type 2 diabetes</li>
                                    <li>Better overall metabolic health</li>
                                </ul>
                            </div>
                        ) : (
                            <div className="text-sm text-gray-700 dark:text-gray-300">
                                <p className="mb-2">
                                    {isPredictedEntry ? 'At this predicted date, your' : isHistoricalView ? 'At this date, your' : 'Your'} waist-to-height ratio indicates increased health risks:
                                </p>
                                <ul className="list-disc list-inside space-y-1 text-xs mb-3">
                                    <li>Higher risk of diabetes</li>
                                    <li>Increased risk of heart disease</li>
                                    <li>Higher risk of stroke</li>
                                    <li>Potential impact on life span</li>
                                </ul>
                                <div className="pt-2 border-t border-orange-200 dark:border-orange-700">
                                    <span className="font-medium">{isPredictedEntry ? 'At predicted date, to' : isHistoricalView ? 'At this date, to' : 'To'} reach healthy range:</span>{' '}
                                    <span className="text-orange-600 dark:text-orange-400 font-semibold">
                                        reduce waist by {(displayWaist - healthyWaist).toFixed(1)} cm
                                    </span>
                                </div>
                                <div className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    Target waist circumference: ≤ {healthyWaist.toFixed(1)} cm (WHtR &lt; 0.5)
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Reference Information */}
                    <div className="mt-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div className="text-xs text-gray-600 dark:text-gray-400">
                            <div className="font-medium text-gray-700 dark:text-gray-300 mb-2">
                                About WHtR
                            </div>
                            <p className="mb-2">
                                The Waist-to-Height Ratio is calculated by dividing your waist circumference by your height (both in the same units).
                            </p>
                            <p>
                                A ratio below 0.5 is considered healthy for both men and women worldwide, indicating lower risks of obesity-related diseases.
                            </p>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
        </>
    )
}
