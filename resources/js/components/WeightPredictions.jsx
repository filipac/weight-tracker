import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { TrendingDown, TrendingUp, Target, Calendar, Activity, Info } from 'lucide-react'

export default function WeightPredictions({ predictions }) {
    if (!predictions?.hasEnoughData) {
        return (
            <Card className="mb-6 flex-shrink-0">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Activity className="h-5 w-5 text-blue-600" />
                        Predictions
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p className="text-gray-600 text-center">
                        Add more weight entries to see trend predictions
                    </p>
                </CardContent>
            </Card>
        )
    }

    const {
        nextMonthPrediction,
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
    const trendColor = isLosingWeight ? 'text-green-600' : 'text-red-600'
    const trendBg = isLosingWeight ? 'bg-green-50' : 'bg-red-50'

    return (
        <Card className="flex-shrink-0">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Activity className="h-5 w-5 text-blue-600" />
                    Weight Predictions
                    <div className="flex items-center gap-1">
                        <span className="text-sm font-normal text-gray-500">
                            ({confidence}% confidence • {entryCount} entries)
                        </span>
                        <div className="relative group">
                            <Info className="h-4 w-4 text-gray-400 cursor-help" />
                            <div className="absolute left-1/2 -translate-x-1/2 bottom-full mb-2 w-80 bg-gray-900 text-white text-xs rounded-lg p-3 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-10">
                                <div className="font-semibold mb-2">Confidence Calculation</div>
                                <div className="space-y-1">
                                    <div>• Based on R-squared statistical measure</div>
                                    <div>• 90%+: Very reliable predictions</div>
                                    <div>• 70-89%: Good confidence level</div>
                                    <div>• 50-69%: Moderate uncertainty</div>
                                    <div>• &lt;50%: Low confidence (erratic data)</div>
                                </div>
                                <div className="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
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
                            <Calendar className="h-4 w-4 text-gray-600" />
                            <span className="text-sm font-medium text-gray-700">
                                {nextMonthDate}
                            </span>
                        </div>
                        <div className="flex items-center gap-2">
                            <TrendIcon className={`h-5 w-5 ${trendColor}`} />
                            <span className="text-2xl font-bold text-gray-900">
                                {nextMonthPrediction} kg
                            </span>
                        </div>
                    </div>

                    {/* Daily Rate */}
                    <div className="bg-blue-50 rounded-lg p-4">
                        <div className="flex items-center gap-2 mb-2">
                            <Activity className="h-4 w-4 text-blue-600" />
                            <span className="text-sm font-medium text-gray-700">
                                Daily Rate
                            </span>
                        </div>
                        <div className="text-2xl font-bold text-gray-900">
                            {isLosingWeight ? '-' : '+'}{dailyWeightLoss} kg/day
                        </div>
                    </div>

                    {/* Custom Goal Predictions */}
                    {goalPredictions.length > 0 ? (
                        goalPredictions.map((goalPrediction, index) => {
                            if (!goalPrediction.prediction_date) return null
                            
                            const getGoalColor = (goalType) => {
                                switch (goalType) {
                                    case 'lose': return 'bg-green-50 border-green-200 text-green-700'
                                    case 'gain': return 'bg-blue-50 border-blue-200 text-blue-700'
                                    case 'maintain': return 'bg-yellow-50 border-yellow-200 text-yellow-700'
                                    default: return 'bg-purple-50 border-purple-200 text-purple-700'
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
                                <div key={goalPrediction.id} className={`rounded-lg p-4 border-2 ${getGoalColor(goalPrediction.goal_type)}`}>
                                    <div className="flex items-center gap-2 mb-2">
                                        <Target className="h-4 w-4" />
                                        <span className="text-sm font-medium">
                                            {getGoalAction(goalPrediction.goal_type)} {goalPrediction.target_weight}kg goal
                                        </span>
                                    </div>
                                    {goalPrediction.description && (
                                        <p className="text-xs mb-2 opacity-75">
                                            {goalPrediction.description}
                                        </p>
                                    )}
                                    <div className="text-xl font-bold">
                                        {goalPrediction.prediction_date}
                                    </div>
                                </div>
                            )
                        })
                    ) : (
                        <>
                            {/* Fallback: Legacy 100kg and 90kg goals */}
                            {goalDate && isLosingWeight && (
                                <div className="bg-purple-50 rounded-lg p-4">
                                    <div className="flex items-center gap-2 mb-2">
                                        <Target className="h-4 w-4 text-purple-600" />
                                        <span className="text-sm font-medium text-gray-700">
                                            Reach 100 kg goal
                                        </span>
                                    </div>
                                    <div className="text-xl font-bold text-gray-900">
                                        {goalDate}
                                    </div>
                                </div>
                            )}

                            {goalDate90 && isLosingWeight && (
                                <div className="bg-orange-50 rounded-lg p-4">
                                    <div className="flex items-center gap-2 mb-2">
                                        <Target className="h-4 w-4 text-orange-600" />
                                        <span className="text-sm font-medium text-gray-700">
                                            Reach 90 kg goal
                                        </span>
                                    </div>
                                    <div className="text-xl font-bold text-gray-900">
                                        {goalDate90}
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    {/* No Goal Message */}
                    {goalPredictions.length === 0 && (!goalDate && !goalDate90) && (
                        <div className="bg-gray-50 rounded-lg p-4 md:col-span-2">
                            <div className="flex items-center gap-2 mb-2">
                                <Target className="h-4 w-4 text-gray-400" />
                                <span className="text-sm font-medium text-gray-700">
                                    No Goal Predictions
                                </span>
                            </div>
                            <div className="text-gray-600">
                                Set up weight goals in the Goals section to see prediction dates here.
                            </div>
                        </div>
                    )}
                </div>

                {/* Confidence Indicator */}
                {confidence < 70 && (
                    <div className="mt-4 p-3 bg-yellow-50 rounded-lg">
                        <p className="text-sm text-yellow-800">
                            <strong>Note:</strong> Predictions have low confidence ({confidence}%). 
                            More consistent weight entries will improve accuracy.
                        </p>
                    </div>
                )}
            </CardContent>
        </Card>
    )
}