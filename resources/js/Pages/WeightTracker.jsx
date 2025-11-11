import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import WeightChart from '@/components/WeightChart'
import WeightEntryForm from '@/components/WeightEntryForm'
import WeightHistoryList from '@/components/WeightHistoryList'
import WeightPredictions from '@/components/WeightPredictions'
import GoalSetting from '@/components/GoalSetting'
import Achievements from '@/components/Achievements'
import WeightChangesWidget from '@/components/WeightChangesWidget'
import { WeightProvider } from '@/contexts/WeightContext'

export default function WeightTracker({
    weightListWithIds,
    chartData,
    predictions,
    goals = [],
    achievements = [],
    currentStreak = 0,
    motivationalMessage = '',
    weightChanges = null
}) {

    return (
        <WeightProvider weightChanges={weightChanges}>
            <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
                <div className="max-w-7xl mx-auto px-4 py-8">
                    <div className="text-center mb-8">
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">Weight Tracker</h1>
                        <p className="text-gray-600 dark:text-gray-400 mt-2">Track your weight loss journey</p>
                    </div>

                    {/* Mobile: Single column, Desktop: Multi-column layout */}
                    <div className="space-y-8">
                        {/* Weight Changes Widget - Full Width */}
                        <WeightChangesWidget />

                        {/* Top Section - 2 Column Layout */}
                        <div className="grid grid-cols-1 xl:grid-cols-2 gap-8">
                            {/* Left Column - Primary Actions & Data Entry */}
                            <div className="xl:col-span-1 space-y-6">
                                <WeightEntryForm />

                                <Achievements
                                    achievements={achievements}
                                    currentStreak={currentStreak}
                                    motivationalMessage={motivationalMessage}
                                />
                            </div>

                            {/* Right Column - Goals & Predictions */}
                            <div className="xl:col-span-1 space-y-6">
                                <GoalSetting goals={goals} />

                                <WeightPredictions predictions={predictions} />
                            </div>
                        </div>

                        {/* Full Width - Weight Progress Chart */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Weight Progress</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <WeightChart data={chartData || []} />
                            </CardContent>
                        </Card>

                        {/* Full Width - Weight History */}
                        <WeightHistoryList weightListWithIds={weightListWithIds} />
                    </div>
                </div>
            </div>
        </WeightProvider>
    )
}
