import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { TrendingDown, TrendingUp, Ruler } from 'lucide-react'

export default function WaistChangesWidget({ waistChanges, compact = false }) {
    if (!waistChanges) {
        return null
    }

    const {
        current_waist,
        current_date,
        current_whtr,
        starting_waist,
        starting_date,
        starting_whtr,
        total_change,
        recent_change,
        total_measurements
    } = waistChanges

    const formatChange = (change) => {
        if (change === null || change === undefined) return 'N/A'
        const sign = change > 0 ? '+' : ''
        return `${sign}${change.toFixed(1)}`
    }

    const getChangeColor = (change) => {
        if (change === null || change === undefined) return 'text-slate-500 dark:text-slate-400'
        // For waist, negative is good (reduction), positive is bad (increase)
        if (change < 0) return 'text-green-500 dark:text-green-400'
        if (change > 0) return 'text-red-500 dark:text-red-400'
        return 'text-slate-500 dark:text-slate-400'
    }

    const getWHtRColor = (whtr) => {
        if (!whtr) return 'text-slate-600 dark:text-slate-400'
        if (whtr < 0.5) return 'text-green-600 dark:text-green-400'
        if (whtr < 0.6) return 'text-yellow-600 dark:text-yellow-400'
        if (whtr < 0.7) return 'text-orange-600 dark:text-orange-400'
        return 'text-red-600 dark:text-red-400'
    }

    const ChangeIcon = ({ change }) => {
        if (change === null || change === undefined) return null
        // For waist, down arrow is good (reduction)
        if (change < 0) return <TrendingDown className="h-3 w-3 text-green-500 dark:text-green-400" />
        if (change > 0) return <TrendingUp className="h-3 w-3 text-red-500 dark:text-red-400" />
        return null
    }

    const mainGridClass = compact
        ? 'grid grid-cols-1 divide-y divide-slate-200 @[20rem]:grid-cols-2 @[20rem]:divide-x @[20rem]:divide-y-0 dark:divide-slate-800'
        : 'grid grid-cols-1 divide-y divide-slate-200 @[40rem]:grid-cols-2 @[40rem]:divide-x @[40rem]:divide-y-0 @[72rem]:grid-cols-4 dark:divide-slate-800'

    const valueClass = compact
        ? 'mb-0.5 text-base font-bold text-slate-900 dark:text-slate-100'
        : 'mb-0.5 text-lg @[56rem]:text-xl font-bold text-slate-900 dark:text-slate-100'

    const deltaValueClass = compact
        ? 'text-base font-bold'
        : 'text-lg @[56rem]:text-xl font-bold'

    return (
        <Card className="@container">
            <CardHeader className="pb-3">
                <CardTitle className="flex items-center gap-2 text-lg">
                    <Ruler className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    Waist Measurements Overview
                </CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                <div className={mainGridClass}>
                    {/* Starting Waist Section */}
                    <div className="px-4 py-3">
                        <div className="mb-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Starting Waist
                        </div>
                        <div className={valueClass}>
                            {starting_waist} cm
                        </div>
                        {starting_whtr && (
                            <div className={`text-sm mb-0.5 ${getWHtRColor(starting_whtr)}`}>
                                WHtR: {starting_whtr}
                            </div>
                        )}
                        <div className="text-xs text-slate-500 dark:text-slate-400">
                            {starting_date}
                        </div>
                    </div>

                    {/* Current Waist Section */}
                    <div className="px-4 py-3">
                        <div className="mb-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Current Waist
                        </div>
                        <div className={valueClass}>
                            {current_waist} cm
                        </div>
                        {current_whtr && (
                            <div className={`text-sm mb-0.5 ${getWHtRColor(current_whtr)}`}>
                                WHtR: {current_whtr}
                            </div>
                        )}
                        {recent_change !== null && recent_change !== undefined && (
                            <div className="flex items-center gap-1 text-xs mb-0.5">
                                <ChangeIcon change={recent_change} />
                                <span className={getChangeColor(recent_change)}>
                                    {formatChange(recent_change)} cm
                                </span>
                            </div>
                        )}
                        <div className="text-xs text-slate-500 dark:text-slate-400">
                            {current_date}
                        </div>
                    </div>

                    {/* Total Change Section */}
                    <div className="px-4 py-3">
                        <div className="mb-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Total Change
                        </div>
                        <div className="flex items-center gap-1.5 mb-0.5">
                            <span className={`${deltaValueClass} ${getChangeColor(total_change)}`}>
                                {formatChange(total_change)} cm
                            </span>
                            <ChangeIcon change={total_change} />
                        </div>
                        {total_change !== null && total_change < 0 && (
                            <div className="text-xs text-green-600 dark:text-green-400 font-medium">
                                {Math.abs(total_change).toFixed(1)} cm lost! 🎉
                            </div>
                        )}
                        {total_change !== null && total_change > 0 && (
                            <div className="text-xs text-red-600 dark:text-red-400">
                                {total_change.toFixed(1)} cm gained
                            </div>
                        )}
                        {total_change === 0 && (
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                No change
                            </div>
                        )}
                    </div>

                    {/* Stats Section */}
                    <div className="px-4 py-3">
                        <div className="mb-0.5 text-xs text-slate-500 dark:text-slate-400">
                            Measurements
                        </div>
                        <div className={compact ? 'mb-2 text-base font-bold text-slate-900 dark:text-slate-100' : 'mb-2 text-lg @[56rem]:text-xl font-bold text-slate-900 dark:text-slate-100'}>
                            {total_measurements}
                        </div>
                        {starting_whtr && current_whtr && (
                            <div className="text-xs text-slate-600 dark:text-slate-400">
                                WHtR: {starting_whtr} → {current_whtr}
                            </div>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    )
}
