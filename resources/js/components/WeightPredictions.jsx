import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { TrendingDown, TrendingUp, Target, Calendar, Activity, Info } from 'lucide-react'

export default function WeightPredictions({ predictions }) {
    if (!predictions?.hasEnoughData) {
        return (
            <Card className="mb-6 flex-shrink-0">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Activity className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        Predictions
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-center text-slate-600 dark:text-slate-300">
                        Add more weight entries to see trend predictions
                    </p>
                </CardContent>
            </Card>
        )
    }

    const {
        nextMonthPrediction,
        nextMonthBMI,
        nextMonthDate,
        goalDate,
        goalDate90,
        goalPredictions = [],
        dailyWeightLoss,
        confidence,
        trend,
        entryCount
    } = predictions

    const isLosingWeight = trend === 'losing'
    const TrendIcon = isLosingWeight ? TrendingDown : TrendingUp
    const trendColor = isLosingWeight ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'
    const trendBg = isLosingWeight ? 'bg-green-50 dark:bg-green-950/30' : 'bg-red-50 dark:bg-red-950/30'

    return (
        <Card className="flex-shrink-0">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Activity className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    Weight Predictions
                    <div className="flex items-center gap-1">
                        <span className="text-sm font-normal text-slate-500 dark:text-slate-400">
                            ({confidence}% confidence • {entryCount} entries)
                        </span>
                        <div className="relative group">
                            <Info className="h-4 w-4 cursor-help text-slate-400 dark:text-slate-500" />
                            <div className="absolute bottom-full left-1/2 z-10 mb-2 w-80 -translate-x-1/2 invisible rounded-lg bg-slate-900 p-3 text-xs text-slate-100 opacity-0 transition-all duration-200 group-hover:visible group-hover:opacity-100 dark:bg-slate-800 dark:text-slate-200">
                                <div className="font-semibold mb-2">Confidence Calculation</div>
                                <div className="space-y-1">
                                    <div>• Based on R-squared statistical measure</div>
                                    <div>• 90%+: Very reliable predictions</div>
                                    <div>• 70-89%: Good confidence level</div>
                                    <div>• 50-69%: Moderate uncertainty</div>
                                    <div>• &lt;50%: Low confidence (erratic data)</div>
                                </div>
                                <div className="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-slate-900 dark:border-t-slate-800"></div>
                            </div>
                        </div>
                    </div>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {/* Next Month Prediction */}
                    <div className={`${trendBg} rounded-lg p-4`}>
                        <div className="flex items-center gap-2 mb-2">
                            <Calendar className="h-4 w-4 text-slate-600 dark:text-slate-300" />
                            <span className="text-sm font-medium text-slate-700 dark:text-slate-300">
                                {nextMonthDate}
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <TrendIcon className={`h-5 w-5 ${trendColor}`} />
                            <span className="text-2xl font-bold text-slate-900 dark:text-slate-100">
                                {nextMonthPrediction} kg
                            </span>
                        </div>
                        {nextMonthBMI && (
                            <div className="text-sm text-blue-600 dark:text-blue-400 mt-1">
                                BMI: {nextMonthBMI}
                            </div>
                        )}
                    </div>

                    {/* Daily Rate */}
                    <div className="rounded-lg bg-blue-50 p-4 dark:bg-blue-950/30">
                        <div className="flex items-center gap-2 mb-2">
                            <Activity className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            <span className="text-sm font-medium text-slate-700 dark:text-slate-300">
                                Daily Rate
                            </span>
                        </div>
                        <div className="text-2xl font-bold text-slate-900 dark:text-slate-100">
                            {isLosingWeight ? '-' : '+'}{dailyWeightLoss} kg/day
                        </div>
                    </div>

                    {/* Custom Goal Predictions */}
                    {goalPredictions.length > 0 ? (
                        goalPredictions.map((goalPrediction, index) => {
                            if (!goalPrediction.prediction_date) return null

                            const getGoalColor = (goalType) => {
                                switch (goalType) {
                                    case 'lose': return 'bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-800 text-green-700 dark:text-green-300'
                                    case 'gain': return 'bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-800 text-blue-700 dark:text-blue-300'
                                    case 'maintain': return 'bg-yellow-50 dark:bg-yellow-950/30 border-yellow-200 dark:border-yellow-800 text-yellow-700 dark:text-yellow-300'
                                    default: return 'bg-indigo-50 dark:bg-indigo-950/30 border-indigo-200 dark:border-indigo-800 text-indigo-700 dark:text-indigo-300'
                                }
                            }

                            const getGoalAction = (goalType) => {
                                switch (goalType) {
                                    case 'lose': return 'Reach'
                                    case 'gain': return 'Reach'
                                    case 'maintain': return 'Maintain'
                                    default: return 'Reach'
                                }
                            }

                            return (
                                <div key={`${goalPrediction.id ?? 'goal'}-${goalPrediction.prediction_date}-${index}`} className={`rounded-lg p-4 border-2 ${getGoalColor(goalPrediction.goal_type)}`}>
                                    <div className="flex items-center gap-2 mb-2">
                                        <Target className="h-4 w-4" />
                                        <span className="text-sm font-medium">
                                            {getGoalAction(goalPrediction.goal_type)} {goalPrediction.target_weight}kg goal
                                        </span>
                                    </div>
                                    {goalPrediction.goal_bmi && (
                                        <div className="text-xs text-blue-600 dark:text-blue-400 mb-2">
                                            Target BMI: {goalPrediction.goal_bmi}
                                        </div>
                                    )}
                                    {goalPrediction.description && (
                                        <p className="text-xs mb-2 opacity-75 dark:opacity-60">
                                            {goalPrediction.description}
                                        </p>
                                    )}
                                    <div className="text-xl font-bold text-slate-900 dark:text-slate-100">
                                        {goalPrediction.prediction_date}
                                    </div>
                                </div>
                            )
                        })
                    ) : (
                        <>
                            {/* Fallback: Legacy 100kg and 90kg goals */}
                            {goalDate && isLosingWeight && (
                                <div className="rounded-lg bg-blue-50 p-4 dark:bg-blue-950/30">
                                    <div className="flex items-center gap-2 mb-2">
                                        <Target className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                        <span className="text-sm font-medium text-slate-700 dark:text-slate-300">
                                            Reach 100 kg goal
                                        </span>
                                    </div>
                                    <div className="text-xl font-bold text-slate-900 dark:text-slate-100">
                                        {goalDate}
                                    </div>
                                </div>
                            )}

                            {goalDate90 && isLosingWeight && (
                                <div className="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4">
                                    <div className="flex items-center gap-2 mb-2">
                                        <Target className="h-4 w-4 text-orange-600 dark:text-orange-400" />
                                        <span className="text-sm font-medium text-slate-700 dark:text-slate-300">
                                            Reach 90 kg goal
                                        </span>
                                    </div>
                                    <div className="text-xl font-bold text-slate-900 dark:text-slate-100">
                                        {goalDate90}
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    {/* No Goal Message */}
                    {goalPredictions.length === 0 && (!goalDate && !goalDate90) && (
                        <div className="rounded-lg bg-slate-50 p-4 md:col-span-2 dark:bg-slate-900/60">
                            <div className="flex items-center gap-2 mb-2">
                                <Target className="h-4 w-4 text-slate-400 dark:text-slate-500" />
                                <span className="text-sm font-medium text-slate-700 dark:text-slate-300">
                                    No Goal Predictions
                                </span>
                            </div>
                            <div className="text-slate-600 dark:text-slate-300">
                                Set up weight goals in the Goals section to see prediction dates here.
                            </div>
                        </div>
                    )}
                </div>

                {/* Confidence Indicator */}
                {confidence < 70 && (
                    <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/30">
                        <p className="text-sm text-amber-800 dark:text-amber-300">
                            <strong>Note:</strong> Predictions have low confidence ({confidence}%).
                            More consistent weight entries will improve accuracy.
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    )
}
