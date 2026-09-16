import { useState, useEffect, useMemo } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Activity, Target, History } from 'lucide-react'

export default function BMIVisualization({ currentBMI, currentWeight, startingBMI, startingWeight, startingDate, goalBMI, healthyBMIDate, healthyBMIWeight, chartData = [], predictions = null }) {
    // State for historical navigation
    const [selectedIndex, setSelectedIndex] = useState(null)
    const [isHistoricalView, setIsHistoricalView] = useState(false)
    const [isFutureView, setIsFutureView] = useState(false)

    // Height hardcoded to 173cm = 1.73m
    const heightInMeters = 1.73

    // Generate combined data with future predictions
    const combinedData = useMemo(() => {
        if (!chartData.length) return []

        // Start with historical data
        const historicalData = chartData.map(entry => ({
            ...entry,
            type: 'historical'
        }))

        // Check if we have prediction data to extend into the future
        if (!predictions?.hasEnoughData || !predictions?.dailyWeightChange || !predictions?.goalPredictions?.length) {
            return historicalData
        }

        // Find the furthest goal date among all goal predictions (lowest weight goal = furthest date typically)
        let furthestGoalDate = null
        let furthestGoalWeight = null

        predictions.goalPredictions.forEach(goal => {
            if (goal.prediction_date_raw) {
                const goalDate = new Date(goal.prediction_date_raw)
                if (!furthestGoalDate || goalDate > furthestGoalDate) {
                    furthestGoalDate = goalDate
                    furthestGoalWeight = goal.target_weight
                }
            }
        })

        // If no goal dates available, don't extend
        if (!furthestGoalDate) {
            return historicalData
        }

        // Get the latest entry as starting point for predictions
        const latestEntry = chartData[chartData.length - 1]
        const latestDate = new Date(latestEntry.date)
        const latestWeight = parseFloat(latestEntry.weight)
        const dailyChange = predictions.dailyWeightChange

        // Generate future predictions (one point per day for granular slider control)
        const futureData = []
        const msPerDay = 24 * 60 * 60 * 1000
        const daysBetween = Math.ceil((furthestGoalDate - latestDate) / msPerDay)

        // Generate daily points for smooth slider and larger future section
        const interval = 1 // days
        for (let day = interval; day <= daysBetween; day += interval) {
            const futureDate = new Date(latestDate.getTime() + day * msPerDay)
            const predictedWeight = latestWeight + (dailyChange * day)

            // Don't go below goal weight if losing, or above if gaining
            const finalWeight = dailyChange < 0
                ? Math.max(predictedWeight, furthestGoalWeight)
                : Math.min(predictedWeight, furthestGoalWeight)

            futureData.push({
                date: futureDate.toISOString().split('T')[0],
                weight: Math.round(finalWeight * 100) / 100,
                type: 'predicted'
            })
        }

        // Add the final goal date point if not already included
        const lastFutureDate = futureData.length > 0 ? new Date(futureData[futureData.length - 1].date) : latestDate
        if (furthestGoalDate > lastFutureDate) {
            futureData.push({
                date: furthestGoalDate.toISOString().split('T')[0],
                weight: furthestGoalWeight,
                type: 'predicted'
            })
        }

        return [...historicalData, ...futureData]
    }, [chartData, predictions])

    // Find the index of the last historical entry
    const lastHistoricalIndex = useMemo(() => {
        return combinedData.findIndex((entry, index) => {
            const nextEntry = combinedData[index + 1]
            return nextEntry && nextEntry.type === 'predicted'
        })
    }, [combinedData])

    // The "current" index is the last historical entry
    const currentIndex = lastHistoricalIndex >= 0 ? lastHistoricalIndex : chartData.length - 1

    // Initialize to current (latest actual entry) when combinedData is available
    useEffect(() => {
        if (combinedData.length > 0 && selectedIndex === null) {
            // Default to the last historical entry (current weight)
            const historicalEndIndex = combinedData.findIndex(e => e.type === 'predicted') - 1
            setSelectedIndex(historicalEndIndex >= 0 ? historicalEndIndex : combinedData.length - 1)
        }
    }, [combinedData])

    // Calculate BMI from weight
    const calculateBMI = (weight) => {
        return Math.round((weight / (heightInMeters * heightInMeters)) * 10) / 10
    }

    // Get the selected weight entry from combined data
    const selectedEntry = combinedData.length > 0 && selectedIndex !== null
        ? combinedData[selectedIndex]
        : null

    // Determine if we're in historical view, current view, or future prediction view
    const isPredictedEntry = selectedEntry?.type === 'predicted'
    const isCurrentEntry = selectedIndex === currentIndex

    // Use the selected data for display
    const displayBMI = selectedEntry
        ? calculateBMI(selectedEntry.weight)
        : currentBMI
    const displayWeight = selectedEntry
        ? parseFloat(selectedEntry.weight)
        : parseFloat(currentWeight)
    const displayDate = selectedEntry
        ? isPredictedEntry
            ? `${new Date(selectedEntry.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })} (Predicted)`
            : isCurrentEntry
                ? 'Current'
                : new Date(selectedEntry.date).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' })
        : 'Current'

    if (!currentBMI) {
        return null
    }

    // BMI ranges with colors
    const ranges = [
        { label: 'Underweight', min: 0, max: 18.5, color: 'bg-blue-200 dark:bg-blue-900' },
        { label: 'Healthy Weight', min: 18.5, max: 25, color: 'bg-green-200 dark:bg-green-900' },
        { label: 'Overweight', min: 25, max: 30, color: 'bg-yellow-200 dark:bg-yellow-900' },
        { label: 'Obesity Class 1', min: 30, max: 35, color: 'bg-orange-200 dark:bg-orange-900' },
        { label: 'Obesity Class 2', min: 35, max: 40, color: 'bg-red-200 dark:bg-red-900' },
        { label: 'Obesity Class 3', min: 40, max: 50, color: 'bg-red-400 dark:bg-red-950' }
    ]

    // Calculate healthy weight range (BMI 18.5 - 25 for 173cm height)
    const minHealthyWeight = 18.5 * (heightInMeters * heightInMeters)
    const maxHealthyWeight = 25 * (heightInMeters * heightInMeters)

    // Calculate how much weight to lose/gain to reach healthy range
    let weightToHealthy = null
    let healthyRangeMessage = null

    if (currentWeight) {
        if (currentBMI < 18.5) {
            // Underweight - need to gain
            weightToHealthy = minHealthyWeight - currentWeight
            healthyRangeMessage = `gain ${Math.abs(weightToHealthy).toFixed(1)} kg`
        } else if (currentBMI >= 25) {
            // Overweight or obese - need to lose
            weightToHealthy = currentWeight - maxHealthyWeight
            healthyRangeMessage = `lose ${weightToHealthy.toFixed(1)} kg`
        } else {
            // Already in healthy range
            healthyRangeMessage = 'already in healthy range'
        }
    }

    // Calculate total range for visualization (0-50 BMI)
    const minBMI = 0
    const maxBMI = 50

    // Calculate position as percentage
    const getPosition = (bmi) => {
        return ((bmi - minBMI) / (maxBMI - minBMI)) * 100
    }

    // Get category name for a BMI value
    const getCategory = (bmi) => {
        const range = ranges.find(r => bmi >= r.min && bmi < r.max)
        return range ? range.label : 'Out of range'
    }

    // Get category color
    const getCategoryColor = (bmi) => {
        const range = ranges.find(r => bmi >= r.min && bmi < r.max)
        if (!range) return 'text-gray-600 dark:text-gray-400'

        if (range.label === 'Healthy Weight') return 'text-green-600 dark:text-green-400'
        if (range.label === 'Underweight') return 'text-blue-600 dark:text-blue-400'
        if (range.label === 'Overweight') return 'text-yellow-600 dark:text-yellow-400'
        if (range.label.includes('Obesity')) return 'text-red-600 dark:text-red-400'
        return 'text-gray-600 dark:text-gray-400'
    }

    const currentPosition = getPosition(displayBMI)
    const goalPosition = goalBMI ? getPosition(goalBMI) : null
    const startingPosition = startingBMI ? getPosition(startingBMI) : null

    // Calculate BMI change
    const bmiChange = startingBMI ? (displayBMI - startingBMI) : null

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
                        <Activity className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        BMI Category Visualization
                    </CardTitle>
                </CardHeader>
                <CardContent>
                {/* Current and Goal BMI Info */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                    {startingBMI && (
                        <div className="bg-purple-50 dark:bg-purple-900/20 rounded-lg p-4">
                            <div className="flex items-center gap-2 mb-2">
                                <Activity className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Starting BMI
                                </span>
                            </div>
                            <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {startingBMI}
                            </div>
                            <div className={`text-sm font-medium mt-1 ${getCategoryColor(startingBMI)}`}>
                                {getCategory(startingBMI)}
                            </div>
                            <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                {startingDate}
                            </div>
                        </div>
                    )}

                    <div className={`rounded-lg p-4 ${isPredictedEntry ? 'bg-emerald-50 dark:bg-emerald-900/20' : 'bg-blue-50 dark:bg-blue-900/20'}`}>
                        <div className="flex items-center gap-2 mb-2">
                            <Activity className={`h-4 w-4 ${isPredictedEntry ? 'text-emerald-600 dark:text-emerald-400' : 'text-blue-600 dark:text-blue-400'}`} />
                            <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                {isPredictedEntry ? 'Predicted BMI' : isHistoricalView ? 'Historical BMI' : 'Current BMI'}
                            </span>
                            {isPredictedEntry && (
                                <span className="text-xs px-1.5 py-0.5 rounded bg-emerald-100 dark:bg-emerald-800 text-emerald-700 dark:text-emerald-300">
                                    Future
                                </span>
                            )}
                        </div>
                        <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {displayBMI}
                        </div>
                        <div className={`text-sm font-medium mt-1 ${getCategoryColor(displayBMI)}`}>
                            {getCategory(displayBMI)}
                        </div>
                        <div className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            {displayDate} • {displayWeight.toFixed(1)} kg
                        </div>
                        {bmiChange !== null && (
                            <div className={`text-xs font-semibold mt-1 ${bmiChange < 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                {bmiChange > 0 ? '+' : ''}{bmiChange.toFixed(1)} from start
                            </div>
                        )}
                    </div>

                    {goalBMI && (
                        <div className="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                            <div className="flex items-center gap-2 mb-2">
                                <Target className="h-4 w-4 text-green-600 dark:text-green-400" />
                                <span className="text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Goal BMI
                                </span>
                            </div>
                            <div className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                {goalBMI}
                            </div>
                            <div className={`text-sm font-medium mt-1 ${getCategoryColor(goalBMI)}`}>
                                {getCategory(goalBMI)}
                            </div>
                        </div>
                    )}
                </div>

                {/* BMI Timeline Navigator (Historical + Future Predictions) */}
                {combinedData.length > 1 && (
                    <div className={`mt-6 p-4 border rounded-lg ${isPredictedEntry ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800' : 'bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800'}`}>
                        <div className="flex items-center gap-2 mb-3">
                            <History className={`h-4 w-4 ${isPredictedEntry ? 'text-emerald-600 dark:text-emerald-400' : 'text-purple-600 dark:text-purple-400'}`} />
                            <span className={`text-sm font-medium ${isPredictedEntry ? 'text-emerald-800 dark:text-emerald-300' : 'text-purple-800 dark:text-purple-300'}`}>
                                BMI Timeline Navigator
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
                                {/* Calculate where the historical/predicted boundary is */}
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
                                            Current Weight
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

                {/* BMI Range Visualization */}
                <div className="space-y-4">
                    <div className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                        BMI Range Categories
                    </div>

                    {/* Visual Bar */}
                    <div className="relative h-16 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700">
                        {/* Range segments */}
                        <div className="absolute inset-0 flex">
                            {ranges.map((range, index) => {
                                const width = ((range.max - range.min) / (maxBMI - minBMI)) * 100
                                return (
                                    <div
                                        key={index}
                                        className={`${range.color} flex items-center justify-center text-xs font-medium text-gray-700 dark:text-gray-300 px-1`}
                                        style={{ width: `${width}%` }}
                                    >
                                        <span className="truncate">{range.min}-{range.max}</span>
                                    </div>
                                )
                            })}
                        </div>

                        {/* Current/Historical/Predicted BMI Marker */}
                        <div
                            className={`absolute top-0 bottom-0 w-1 z-10 transition-all duration-300 ${isPredictedEntry ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-blue-600 dark:bg-blue-400'}`}
                            style={{ left: `${currentPosition}%` }}
                        >
                            <div className="absolute -top-6 left-1/2 -translate-x-1/2 whitespace-nowrap">
                                <div className="flex items-center gap-1">
                                    <Activity className={`h-3 w-3 ${isPredictedEntry ? 'text-emerald-600 dark:text-emerald-400' : 'text-blue-600 dark:text-blue-400'}`} />
                                    <span className={`text-xs font-semibold ${isPredictedEntry ? 'text-emerald-600 dark:text-emerald-400' : 'text-blue-600 dark:text-blue-400'}`}>
                                        {isPredictedEntry ? 'Predicted' : isHistoricalView ? 'Historical' : 'Current'}: {displayBMI}
                                    </span>
                                </div>
                            </div>
                            <div className={`absolute -top-2 left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent ${isPredictedEntry ? 'border-t-emerald-600 dark:border-t-emerald-400' : 'border-t-blue-600 dark:border-t-blue-400'}`}></div>
                        </div>

                        {/* Starting BMI Marker */}
                        {startingPosition !== null && (
                            <div
                                className="absolute top-0 bottom-0 flex items-center justify-center z-10"
                                style={{ left: `${startingPosition}%` }}
                            >
                                <div className="w-3 h-3 rounded-full bg-purple-600 dark:bg-purple-400 border-2 border-white dark:border-gray-900"></div>
                                <div className="absolute top-full mt-2 left-1/2 -translate-x-1/2 whitespace-nowrap">
                                    <div className="flex items-center gap-1">
                                        <span className="text-xs font-semibold text-purple-600 dark:text-purple-400">
                                            Start: {startingBMI}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Goal BMI Marker */}
                        {goalPosition !== null && (
                            <div
                                className="absolute top-0 bottom-0 w-1 bg-green-600 dark:bg-green-400 z-10"
                                style={{ left: `${goalPosition}%` }}
                            >
                                <div className="absolute -bottom-6 left-1/2 -translate-x-1/2 whitespace-nowrap">
                                    <div className="flex items-center gap-1">
                                        <Target className="h-3 w-3 text-green-600 dark:text-green-400" />
                                        <span className="text-xs font-semibold text-green-600 dark:text-green-400">
                                            Goal: {goalBMI}
                                        </span>
                                    </div>
                                </div>
                                <div className="absolute -bottom-2 left-1/2 -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-green-600 dark:border-b-green-400"></div>
                            </div>
                        )}
                    </div>

                    {/* Legend with Markers */}
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-2 mt-8">
                        {ranges.map((range, index) => (
                            <div key={index} className="flex items-center gap-2">
                                <div className={`w-4 h-4 rounded ${range.color}`}></div>
                                <span className="text-xs text-gray-600 dark:text-gray-400">
                                    {range.label}
                                </span>
                            </div>
                        ))}
                    </div>

                    {/* Marker Legend */}
                    <div className="mt-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <div className="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Progress Markers
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
                            {startingBMI && (
                                <div className="flex items-center gap-2">
                                    <div className="w-3 h-3 rounded-full bg-purple-600 dark:bg-purple-400"></div>
                                    <span className="text-xs text-gray-600 dark:text-gray-400">
                                        Starting BMI ({startingBMI})
                                    </span>
                                </div>
                            )}
                            <div className="flex items-center gap-2">
                                <div className={`w-1 h-4 ${isPredictedEntry ? 'bg-emerald-600 dark:bg-emerald-400' : 'bg-blue-600 dark:bg-blue-400'}`}></div>
                                <span className="text-xs text-gray-600 dark:text-gray-400">
                                    {isPredictedEntry ? 'Predicted' : isHistoricalView ? 'Historical' : 'Current'} BMI ({displayBMI})
                                </span>
                            </div>
                            {goalBMI && (
                                <div className="flex items-center gap-2">
                                    <div className="w-1 h-4 bg-green-600 dark:bg-green-400"></div>
                                    <span className="text-xs text-gray-600 dark:text-gray-400">
                                        Goal BMI ({goalBMI})
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Total Progress Summary */}
                    {startingBMI && startingWeight && (
                        <div className={`mt-4 p-4 border rounded-lg ${isPredictedEntry ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800' : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'}`}>
                            <div className={`font-medium mb-3 ${isPredictedEntry ? 'text-emerald-800 dark:text-emerald-300' : 'text-blue-800 dark:text-blue-300'}`}>
                                {isPredictedEntry ? 'Predicted Progress by Selected Date' : isHistoricalView ? 'Progress to Selected Date' : 'Total Progress Since Start'}
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <div className="text-xs text-gray-600 dark:text-gray-400 mb-1">
                                        BMI Change
                                    </div>
                                    <div className={`text-2xl font-bold ${bmiChange < 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                        {bmiChange > 0 ? '+' : ''}{bmiChange.toFixed(1)}
                                    </div>
                                    <div className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                        {startingBMI} → {displayBMI}
                                    </div>
                                </div>
                                <div>
                                    <div className="text-xs text-gray-600 dark:text-gray-400 mb-1">
                                        Weight Change
                                    </div>
                                    <div className={`text-2xl font-bold ${(displayWeight - parseFloat(startingWeight)) < 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                        {(displayWeight - parseFloat(startingWeight)) > 0 ? '+' : ''}{(displayWeight - parseFloat(startingWeight)).toFixed(1)} kg
                                    </div>
                                    <div className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                        {parseFloat(startingWeight).toFixed(1)} → {displayWeight.toFixed(1)} kg
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Healthy Weight Range Info */}
                    <div className="mt-4 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg space-y-2">
                        <div className="font-medium text-green-800 dark:text-green-300 mb-2">
                            Healthy Weight Range (for 173cm height)
                        </div>
                        <div className="text-sm text-gray-700 dark:text-gray-300">
                            <span className="font-medium">Target Range:</span>{' '}
                            <span className="text-green-600 dark:text-green-400 font-semibold">
                                {minHealthyWeight.toFixed(1)} - {maxHealthyWeight.toFixed(1)} kg
                            </span>
                        </div>
                        <div className="text-sm text-gray-700 dark:text-gray-300">
                            <span className="font-medium">BMI Range:</span>{' '}
                            <span className="text-green-600 dark:text-green-400 font-semibold">
                                18.5 - 25.0
                            </span>
                        </div>
                        {displayWeight && (
                            <div className="text-sm text-gray-700 dark:text-gray-300 pt-2 border-t border-green-200 dark:border-green-700">
                                <span className="font-medium">{isPredictedEntry ? 'At predicted date' : isHistoricalView ? 'At selected date' : 'Currently'}, to reach healthy range:</span>{' '}
                                <span className={
                                    displayBMI >= 18.5 && displayBMI < 25
                                        ? 'text-green-600 dark:text-green-400 font-semibold'
                                        : 'text-orange-600 dark:text-orange-400 font-semibold'
                                }>
                                    {displayBMI < 18.5
                                        ? `gain ${Math.abs(minHealthyWeight - displayWeight).toFixed(1)} kg`
                                        : displayBMI >= 25
                                            ? `lose ${(displayWeight - maxHealthyWeight).toFixed(1)} kg`
                                            : 'already in healthy range'
                                    }
                                </span>
                            </div>
                        )}
                        {healthyBMIDate && !isHistoricalView && currentBMI >= 25 && (
                            <div className="text-sm text-gray-700 dark:text-gray-300 pt-2 mt-2 border-t border-green-200 dark:border-green-700">
                                <span className="font-medium">Predicted date to reach healthy BMI:</span>{' '}
                                <span className="text-green-600 dark:text-green-400 font-semibold">
                                    {healthyBMIDate}
                                </span>
                                <div className="text-xs text-gray-600 dark:text-gray-400 mt-1">
                                    (at {healthyBMIWeight} kg, BMI 25.0)
                                </div>
                            </div>
                        )}
                    </div>

                    {/* BMI Difference */}
                    {goalBMI && (
                        <div className="mt-4 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                            <div className="text-sm text-gray-700 dark:text-gray-300">
                                <span className="font-medium">{isPredictedEntry ? 'BMI Change Needed (from predicted date)' : isHistoricalView ? 'BMI Change Needed (from selected date)' : 'BMI Change Needed'}:</span>{' '}
                                <span className={displayBMI > goalBMI ? 'text-green-600 dark:text-green-400' : 'text-blue-600 dark:text-blue-400'}>
                                    {displayBMI > goalBMI ? '-' : '+'}{Math.abs(displayBMI - goalBMI).toFixed(1)} BMI points
                                </span>
                            </div>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
        </>
    )
}
