import { Card, CardContent } from '@/components/ui/card'
import { TrendingDown, TrendingUp } from 'lucide-react'
import { useWeightChanges } from '@/contexts/WeightContext'

export default function WeightChangesWidget() {
    const weightChanges = useWeightChanges()

    if (!weightChanges) {
        return null
    }

    const { current_weight, current_date, recent_change, period_changes } = weightChanges

    const formatChange = (change) => {
        if (change === null || change === undefined) return 'N/A'
        const sign = change > 0 ? '+' : ''
        return `${sign}${change.toFixed(1)}`
    }

    const formatPercentage = (percentage) => {
        if (percentage === null || percentage === undefined) return ''
        const sign = percentage > 0 ? '+' : ''
        return `${sign}${percentage.toFixed(1)}% change`
    }

    const getChangeColor = (change) => {
        if (change === null || change === undefined) return 'text-gray-500 dark:text-gray-400'
        if (change < 0) return 'text-green-500 dark:text-green-400'
        if (change > 0) return 'text-red-500 dark:text-red-400'
        return 'text-gray-500 dark:text-gray-400'
    }

    const ChangeIcon = ({ change }) => {
        if (change === null || change === undefined) return null
        if (change < 0) return <TrendingDown className="h-3 w-3 text-green-500 dark:text-green-400" />
        if (change > 0) return <TrendingUp className="h-3 w-3 text-red-500 dark:text-red-400" />
        return null
    }

    return (
        <Card className="dark:bg-gray-900 dark:border-gray-800">
            <CardContent className="p-0">
                <div className="grid grid-cols-1 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-gray-800">
                    {/* Current Weight Section */}
                    <div className="px-4 py-3">
                        <div className="text-xs text-gray-500 dark:text-gray-400 mb-0.5">
                            Current Weight
                        </div>
                        <div className="text-xl font-bold text-gray-900 dark:text-gray-100 mb-0.5">
                            {current_weight} kg
                        </div>
                        {recent_change !== null && recent_change !== undefined && (
                            <div className="flex items-center gap-1 text-xs mb-0.5">
                                <ChangeIcon change={recent_change} />
                                <span className={getChangeColor(recent_change)}>
                                    {formatChange(recent_change)} kg
                                </span>
                            </div>
                        )}
                        <div className="text-xs text-gray-500 dark:text-gray-400">
                            {current_date}
                        </div>
                    </div>

                    {/* 7 Day Change Section */}
                    <div className="px-4 py-3">
                        <div className="text-xs text-gray-500 dark:text-gray-400 mb-0.5">
                            7 Day Change
                        </div>
                        <div className="flex items-center gap-1.5 mb-0.5">
                            <span className={`text-xl font-bold ${getChangeColor(period_changes[7]?.change)}`}>
                                {formatChange(period_changes[7]?.change)} kg
                            </span>
                            <ChangeIcon change={period_changes[7]?.change} />
                        </div>
                        {period_changes[7]?.percentage !== null && period_changes[7]?.percentage !== undefined && (
                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                {formatPercentage(period_changes[7]?.percentage)}
                            </div>
                        )}
                    </div>

                    {/* 14 Day Change Section */}
                    <div className="px-4 py-3">
                        <div className="text-xs text-gray-500 dark:text-gray-400 mb-0.5">
                            14 Day Change
                        </div>
                        <div className="flex items-center gap-1.5 mb-0.5">
                            <span className={`text-xl font-bold ${getChangeColor(period_changes[14]?.change)}`}>
                                {formatChange(period_changes[14]?.change)} kg
                            </span>
                            <ChangeIcon change={period_changes[14]?.change} />
                        </div>
                        {period_changes[14]?.percentage !== null && period_changes[14]?.percentage !== undefined && (
                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                {formatPercentage(period_changes[14]?.percentage)}
                            </div>
                        )}
                    </div>

                    {/* 30 Day Change Section */}
                    <div className="px-4 py-3">
                        <div className="text-xs text-gray-500 dark:text-gray-400 mb-0.5">
                            30 Day Change
                        </div>
                        <div className="flex items-center gap-1.5 mb-0.5">
                            <span className={`text-xl font-bold ${getChangeColor(period_changes[30]?.change)}`}>
                                {formatChange(period_changes[30]?.change)} kg
                            </span>
                            <ChangeIcon change={period_changes[30]?.change} />
                        </div>
                        {period_changes[30]?.percentage !== null && period_changes[30]?.percentage !== undefined && (
                            <div className="text-xs text-gray-500 dark:text-gray-400">
                                {formatPercentage(period_changes[30]?.percentage)}
                            </div>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    )
}
