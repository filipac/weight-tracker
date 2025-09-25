import { useState, useRef } from 'react'
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts'
import { Button } from '@/components/ui/button'
import { ZoomIn, ZoomOut, RotateCcw, ChevronLeft, ChevronRight } from 'lucide-react'

export default function WeightChart({ data }) {
    const [aggregation, setAggregation] = useState('weekly')
    const [zoomDomain, setZoomDomain] = useState(null)
    const [isZooming, setIsZooming] = useState(false)
    const chartRef = useRef(null)

    // Filter and prepare raw data
    const rawData = data
        .filter(item => item.type !== 'summary' && item.weight && item.date)
        .map(item => {
            const weight = parseFloat(item.weight)
            return {
                date: item.date,
                weight: isNaN(weight) ? 0 : weight,
                dateObj: new Date(item.date)
            }
        })
        .filter(item => item.weight > 0 && item.weight < 500) // Filter out invalid weights
        .sort((a, b) => a.dateObj - b.dateObj)

    // Aggregate data based on selected period
    const aggregateData = (rawData, period) => {
        if (period === 'daily') {
            return rawData.map(item => ({
                ...item,
                displayDate: item.dateObj.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric'
                })
            }))
        }

        const grouped = new Map()

        rawData.forEach(item => {
            let key
            let displayDate

            if (period === 'weekly') {
                // Get start of week (Sunday)
                const startOfWeek = new Date(item.dateObj)
                startOfWeek.setDate(startOfWeek.getDate() - startOfWeek.getDay())
                key = startOfWeek.toISOString().split('T')[0]
                displayDate = startOfWeek.toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric'
                })
            } else if (period === 'monthly') {
                // Get start of month
                const startOfMonth = new Date(item.dateObj.getFullYear(), item.dateObj.getMonth(), 1)
                key = startOfMonth.toISOString().split('T')[0]
                displayDate = startOfMonth.toLocaleDateString('en-US', {
                    month: 'short',
                    year: 'numeric'
                })
            }

            if (!grouped.has(key)) {
                grouped.set(key, { weights: [], date: key, displayDate })
            }
            grouped.get(key).weights.push(item.weight)
        })

        // Calculate averages for each period
        return Array.from(grouped.values()).map(group => {
            const avgWeight = group.weights.reduce((sum, w) => sum + w, 0) / group.weights.length
            return {
                date: group.date,
                weight: Math.round(avgWeight * 100) / 100, // Round to 2 decimal places
                displayDate: group.displayDate,
                count: group.weights.length
            }
        })
    }

    const chartData = aggregateData(rawData, aggregation)

    if (chartData.length === 0) {
        return (
            <div className="flex items-center justify-center h-64 text-gray-500 dark:text-gray-400">
                No weight data available for chart
            </div>
        )
    }

    const minWeight = Math.min(...chartData.map(d => d.weight))
    const maxWeight = Math.max(...chartData.map(d => d.weight))

    // Zoom and pan functions
    const handleZoomIn = () => {
        if (chartData.length < 2) return

        const dataLength = chartData.length
        const currentStart = zoomDomain?.startIndex ?? 0
        const currentEnd = zoomDomain?.endIndex ?? dataLength - 1
        const currentRange = currentEnd - currentStart + 1

        // Prevent zooming too much - minimum of 5 data points
        const minRange = Math.max(5, Math.ceil(dataLength * 0.1))
        if (currentRange <= minRange) return

        const zoomFactor = 0.7 // Zoom to 70% of current range
        const newRange = Math.max(minRange, Math.floor(currentRange * zoomFactor))
        const centerPoint = Math.floor((currentStart + currentEnd) / 2)
        const newStart = Math.max(0, centerPoint - Math.floor(newRange / 2))
        const newEnd = Math.min(dataLength - 1, newStart + newRange - 1)

        // Ensure we have a valid range
        if (newStart <= newEnd && newEnd < dataLength) {
            setZoomDomain({ startIndex: newStart, endIndex: newEnd })
            setIsZooming(true)
        }
    }

    const handleZoomOut = () => {
        if (!zoomDomain) return

        const dataLength = chartData.length
        const currentStart = zoomDomain.startIndex
        const currentEnd = zoomDomain.endIndex
        const currentRange = currentEnd - currentStart + 1

        const zoomFactor = 1.5 // Zoom out to 150% of current range
        const newRange = Math.floor(currentRange * zoomFactor)
        const centerPoint = Math.floor((currentStart + currentEnd) / 2)
        const newStart = Math.max(0, centerPoint - Math.floor(newRange / 2))
        const newEnd = Math.min(dataLength - 1, newStart + newRange - 1)

        if (newStart === 0 && newEnd === dataLength - 1) {
            setZoomDomain(null)
            setIsZooming(false)
        } else if (newStart <= newEnd) {
            setZoomDomain({ startIndex: newStart, endIndex: newEnd })
        }
    }

    const handleResetZoom = () => {
        setZoomDomain(null)
        setIsZooming(false)
    }

    const handlePanLeft = () => {
        if (!zoomDomain) return

        const dataLength = chartData.length
        const currentStart = zoomDomain.startIndex
        const currentEnd = zoomDomain.endIndex
        const range = currentEnd - currentStart + 1

        // Pan by 25% of current range
        const panAmount = Math.max(1, Math.floor(range * 0.25))
        const newStart = Math.max(0, currentStart - panAmount)
        const newEnd = newStart + range - 1

        if (newStart >= 0 && newEnd < dataLength) {
            setZoomDomain({ startIndex: newStart, endIndex: newEnd })
        }
    }

    const handlePanRight = () => {
        if (!zoomDomain) return

        const dataLength = chartData.length
        const currentStart = zoomDomain.startIndex
        const currentEnd = zoomDomain.endIndex
        const range = currentEnd - currentStart + 1

        // Pan by 25% of current range
        const panAmount = Math.max(1, Math.floor(range * 0.25))
        const newStart = currentStart + panAmount
        const newEnd = Math.min(dataLength - 1, newStart + range - 1)
        const actualNewStart = Math.max(0, newEnd - range + 1)

        if (actualNewStart <= newEnd && newEnd < dataLength) {
            setZoomDomain({ startIndex: actualNewStart, endIndex: newEnd })
        }
    }

    const handleMouseWheelZoom = (event) => {
        if (event.ctrlKey || event.metaKey) {
            // Zoom with Ctrl/Cmd held
            event.preventDefault()
            const delta = event.deltaY > 0 ? 1 : -1 // Scroll down = zoom out, up = zoom in

            if (delta > 0) {
                handleZoomOut()
            } else {
                handleZoomIn()
            }
        } else if (zoomDomain) {
            // Pan left/right when zoomed (without Ctrl/Cmd)
            event.preventDefault()
            const delta = event.deltaY > 0 ? 1 : -1 // Scroll down = pan right, up = pan left

            if (delta > 0) {
                handlePanRight()
            } else {
                handlePanLeft()
            }
        }
    }

    const handleKeyNavigation = (event) => {
        if (!zoomDomain) return

        switch (event.key) {
            case 'ArrowLeft':
                event.preventDefault()
                handlePanLeft()
                break
            case 'ArrowRight':
                event.preventDefault()
                handlePanRight()
                break
            case 'Escape':
                event.preventDefault()
                handleResetZoom()
                break
        }
    }

    // Get the data to display based on zoom
    let displayData = chartData
    if (zoomDomain && zoomDomain.startIndex >= 0 && zoomDomain.endIndex < chartData.length && zoomDomain.startIndex <= zoomDomain.endIndex) {
        displayData = chartData.slice(zoomDomain.startIndex, zoomDomain.endIndex + 1)
    }

    // Safety check to ensure we always have data to display
    if (displayData.length === 0 && chartData.length > 0) {
        // Reset zoom if display data is empty
        setTimeout(() => {
            setZoomDomain(null)
            setIsZooming(false)
        }, 0)
        displayData = chartData
    }

    return (
        <div className="w-full">
            <div className="flex justify-between items-center mb-4">
                <div className="flex justify-center space-x-1 bg-gray-100 dark:bg-gray-800 rounded-lg p-1 w-fit mx-auto">
                    <Button
                        variant={aggregation === 'daily' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => {
                            setAggregation('daily')
                            handleResetZoom()
                        }}
                        className={aggregation === 'daily' ? 'bg-white dark:bg-gray-700 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100'}
                    >
                        Daily
                    </Button>
                    <Button
                        variant={aggregation === 'weekly' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => {
                            setAggregation('weekly')
                            handleResetZoom()
                        }}
                        className={aggregation === 'weekly' ? 'bg-white dark:bg-gray-700 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100'}
                    >
                        Weekly
                    </Button>
                    <Button
                        variant={aggregation === 'monthly' ? 'default' : 'ghost'}
                        size="sm"
                        onClick={() => {
                            setAggregation('monthly')
                            handleResetZoom()
                        }}
                        className={aggregation === 'monthly' ? 'bg-white dark:bg-gray-700 shadow-sm' : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100'}
                    >
                        Monthly
                    </Button>
                </div>

                {/* Zoom and Pan Controls */}
                <div className="flex items-center space-x-2">
                    {/* Pan Controls (only show when zoomed) */}
                    {zoomDomain && (
                        <>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handlePanLeft}
                                disabled={zoomDomain.startIndex <= 0}
                                className="p-2"
                                title="Pan Left (←)"
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handlePanRight}
                                disabled={zoomDomain.endIndex >= chartData.length - 1}
                                className="p-2"
                                title="Pan Right (→)"
                            >
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                            <div className="w-px h-6 bg-gray-300 dark:bg-gray-600 mx-1"></div>
                        </>
                    )}

                    {/* Zoom Controls */}
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleZoomIn}
                        disabled={!chartData.length || (zoomDomain && (zoomDomain.endIndex - zoomDomain.startIndex + 1) <= Math.max(5, Math.ceil(chartData.length * 0.1)))}
                        className="p-2"
                        title="Zoom In"
                    >
                        <ZoomIn className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleZoomOut}
                        disabled={!zoomDomain}
                        className="p-2"
                        title="Zoom Out"
                    >
                        <ZoomOut className="h-4 w-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleResetZoom}
                        disabled={!zoomDomain}
                        className="p-2"
                        title="Reset Zoom (Esc)"
                    >
                        <RotateCcw className="h-4 w-4" />
                    </Button>
                </div>
            </div>

            {/* Zoom instructions */}
            <div className="text-xs text-gray-500 dark:text-gray-400 text-center mb-2">
                {zoomDomain ? (
                    `Showing ${displayData.length} of ${chartData.length} data points • Scroll to pan • Ctrl+Scroll to zoom • ← → keys or Esc to reset`
                ) : (
                    `Ctrl+Scroll to zoom in/out on the chart`
                )}
            </div>

            <div
                className="h-64 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:focus:ring-blue-400 focus:ring-opacity-50 rounded-lg"
                onWheel={handleMouseWheelZoom}
                onKeyDown={handleKeyNavigation}
                tabIndex={zoomDomain ? 0 : -1}
                style={{ cursor: zoomDomain ? 'grab' : 'default' }}
            >
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart
                        data={displayData}
                        margin={{ top: 5, right: 30, left: 20, bottom: 5 }}
                        ref={chartRef}
                    >
                        <CartesianGrid strokeDasharray="3 3" className="dark:opacity-20" />
                        <XAxis
                            dataKey="displayDate"
                            tick={{ fontSize: 12, fill: 'currentColor' }}
                            className="text-gray-700 dark:text-gray-300"
                            angle={-45}
                            textAnchor="end"
                            height={60}
                        />
                        <YAxis
                            domain={['dataMin - 5', 'dataMax + 5']}
                            tick={{ fontSize: 12, fill: 'currentColor' }}
                            className="text-gray-700 dark:text-gray-300"
                            label={{ value: 'Weight (kg)', angle: -90, position: 'insideLeft', style: { textAnchor: 'middle', fill: 'currentColor' } }}
                        />
                        <Tooltip
                            formatter={(value) => {
                                const formatted = `${value.toFixed(2)} kg`
                                if (aggregation === 'weekly' || aggregation === 'monthly') {
                                    return [`${formatted} (avg)`, 'Weight']
                                }
                                return [formatted, 'Weight']
                            }}
                            labelFormatter={(label, payload) => {
                                if (payload && payload[0]) {
                                    const data = payload[0].payload
                                    let dateLabel = new Date(data.date).toLocaleDateString('en-US', {
                                        weekday: 'long',
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric'
                                    })

                                    if (aggregation === 'weekly') {
                                        dateLabel = `Week of ${dateLabel}`
                                        if (data.count) {
                                            dateLabel += ` (${data.count} entries)`
                                        }
                                    } else if (aggregation === 'monthly') {
                                        dateLabel = data.displayDate
                                        if (data.count) {
                                            dateLabel += ` (${data.count} entries)`
                                        }
                                    }

                                    return dateLabel
                                }
                                return label
                            }}
                        />
                        <Line
                            type="monotone"
                            dataKey="weight"
                            stroke="#2563eb"
                            strokeWidth={2}
                            dot={{ fill: '#2563eb', strokeWidth: 2, r: 4 }}
                            activeDot={{ r: 6, fill: '#1d4ed8' }}
                        />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    )
}
