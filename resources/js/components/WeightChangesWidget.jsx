import { Card, CardContent } from '@/components/ui/card'
import { TrendingDown, TrendingUp } from 'lucide-react'
import { useWeightChanges } from '@/contexts/WeightContext'

export default function WeightChangesWidget({ compact = false }) {
    const weightChanges = useWeightChanges()

    if (!weightChanges) {
        return null
    }

    const { current_weight, current_date, current_bmi, recent_change, period_changes, seven_day_average, total_entries, unique_days, lowest_30_day_weight, lowest_30_day_date } = weightChanges

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
        if (change === null || change === undefined) return 'text-slate-500 dark:text-slate-400'
        if (change < 0) return 'text-green-500 dark:text-green-400'
        if (change > 0) return 'text-red-500 dark:text-red-400'
        return 'text-slate-500 dark:text-slate-400'
    }

    const ChangeIcon = ({ change }) => {
        if (change === null || change === undefined) return null
        if (change < 0) return <TrendingDown className="h-3 w-3 text-green-500 dark:text-green-400" />
        if (change > 0) return <TrendingUp className="h-3 w-3 text-red-500 dark:text-red-400" />
        return null
    }

    const mainGridClass = compact
        ? 'grid grid-cols-1 divide-y divide-slate-200 @[20rem]:grid-cols-2 @[20rem]:divide-x @[20rem]:divide-y-0 dark:divide-slate-800'
        : 'grid grid-cols-1 divide-y divide-slate-200 @[40rem]:grid-cols-2 @[40rem]:divide-x @[40rem]:divide-y-0 @[72rem]:grid-cols-6 dark:divide-slate-800'

    const statsGridClass = compact
        ? 'grid grid-cols-1 divide-y divide-slate-200 border-t border-slate-200 @[20rem]:grid-cols-2 @[20rem]:divide-x @[20rem]:divide-y-0 dark:divide-slate-800 dark:border-slate-800'
        : 'grid grid-cols-1 divide-y divide-slate-200 border-t border-slate-200 @[40rem]:grid-cols-2 @[40rem]:divide-x @[40rem]:divide-y-0 dark:divide-slate-800 dark:border-slate-800'

    const valueClass = compact
        ? 'mb-0.5 text-base font-bold text-slate-900 dark:text-slate-100'
        : 'mb-0.5 text-lg font-bold text-slate-900 @[56rem]:text-xl dark:text-slate-100'

    const deltaValueClass = compact
        ? 'text-base font-bold'
        : 'text-lg @[56rem]:text-xl font-bold'

    return (
        <Card className="@container">
            <CardContent className="p-0">
                <div className={mainGridClass}>
                    {/* Current Weight Section */}
                    <div className="px-4 py-3">
                        <div className="mb-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Current Weight
                        </div>
                        <div className={valueClass}>
                            {current_weight} kg
                        </div>
                        {current_bmi && (
                            <div className="mb-0.5 text-sm text-blue-700 dark:text-blue-300">
                                BMI: {current_bmi}
                            </div>
                        )}
                        {recent_change !== null && recent_change !== undefined && (
                            <div className="flex items-center gap-1 text-xs mb-0.5">
                                <ChangeIcon change={recent_change} />
                                <span className={getChangeColor(recent_change)}>
                                    {formatChange(recent_change)} kg
                                </span>
                            </div>
                        )}
                        <div className="text-xs text-slate-500 dark:text-slate-400">
                            {current_date}
                        </div>
                    </div>

                    {/* 7 Day Average Section */}
                    <div className="px-4 py-3">
                        <div className="mb-0.5 text-xs text-slate-500 dark:text-slate-400">
                            7 Day Average
                        </div>
                        <div className={valueClass}>
                            {seven_day_average !== null && seven_day_average !== undefined ? `${seven_day_average} kg` : 'N/A'}
                        </div>
                        <div className="text-xs text-slate-500 dark:text-slate-400">
                            Lowest per day
                        </div>
                    </div>

                    {/* 30 Day Lowest Section */}
                    <div className="px-4 py-3">
                        <div className="mb-0.5 text-xs text-slate-500 dark:text-slate-400">
                            30 Day Lowest
                        </div>
                        <div className={`${valueClass} text-green-600 dark:text-green-400`}>
                            {lowest_30_day_weight !== null && lowest_30_day_weight !== undefined ? `${lowest_30_day_weight} kg` : 'N/A'}
                        </div>
                        {lowest_30_day_date && (
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                {lowest_30_day_date}
                            </div>
                        )}
                    </div>

                    {/* 7 Day Change Section */}
                    <div className="px-4 py-3">
                        <div className="mb-0.5 text-xs text-slate-500 dark:text-slate-400">
                            7 Day Change
                        </div>
                        <div className="flex items-center gap-1.5 mb-0.5">
                            <span className={`${deltaValueClass} ${getChangeColor(period_changes[7]?.change)}`}>
                                {formatChange(period_changes[7]?.change)} kg
                            </span>
                            <ChangeIcon change={period_changes[7]?.change} />
                        </div>
                        {period_changes[7]?.percentage !== null && period_changes[7]?.percentage !== undefined && (
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                {formatPercentage(period_changes[7]?.percentage)}
                            </div>
                        )}
                    </div>

                    {/* 14 Day Change Section */}
                    <div className="px-4 py-3">
                        <div className="mb-0.5 text-xs text-slate-500 dark:text-slate-400">
                            14 Day Change
                        </div>
                        <div className="flex items-center gap-1.5 mb-0.5">
                            <span className={`${deltaValueClass} ${getChangeColor(period_changes[14]?.change)}`}>
                                {formatChange(period_changes[14]?.change)} kg
                            </span>
                            <ChangeIcon change={period_changes[14]?.change} />
                        </div>
                        {period_changes[14]?.percentage !== null && period_changes[14]?.percentage !== undefined && (
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                {formatPercentage(period_changes[14]?.percentage)}
                            </div>
                        )}
                    </div>

                    {/* 30 Day Change Section */}
                    <div className="px-4 py-3">
                        <div className="mb-0.5 text-xs text-slate-500 dark:text-slate-400">
                            30 Day Change
                        </div>
                        <div className="flex items-center gap-1.5 mb-0.5">
                            <span className={`${deltaValueClass} ${getChangeColor(period_changes[30]?.change)}`}>
                                {formatChange(period_changes[30]?.change)} kg
                            </span>
                            <ChangeIcon change={period_changes[30]?.change} />
                        </div>
                        {period_changes[30]?.percentage !== null && period_changes[30]?.percentage !== undefined && (
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                {formatPercentage(period_changes[30]?.percentage)}
                            </div>
                        )}
                    </div>
                </div>

                {/* Entry Statistics Row */}
                {(total_entries !== undefined || unique_days !== undefined) && (
                    <div className={statsGridClass}>
                        {/* Total Entries */}
                        <div className="px-4 py-3">
                            <div className="mb-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Total Entries
                            </div>
                            <div className={compact ? 'text-base font-bold text-slate-900 dark:text-slate-100' : 'text-lg @[56rem]:text-xl font-bold text-slate-900 dark:text-slate-100'}>
                                {total_entries?.toLocaleString() || 0}
                            </div>
                        </div>

                        {/* Unique Days */}
                        <div className="px-4 py-3">
                            <div className="mb-0.5 text-xs text-slate-500 dark:text-slate-400">
                                Tracking Days
                            </div>
                            <div className={compact ? 'text-base font-bold text-slate-900 dark:text-slate-100' : 'text-lg @[56rem]:text-xl font-bold text-slate-900 dark:text-slate-100'}>
                                {unique_days?.toLocaleString() || 0}
                            </div>
                        </div>
                    </div>
                )}
            </CardContent>
        </Card>
    )
}
