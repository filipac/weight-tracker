import { useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import WeightChart from '@/components/WeightChart'
import WaistChart from '@/components/WaistChart'
import WeightEntryForm from '@/components/WeightEntryForm'
import WeightHistoryList from '@/components/WeightHistoryList'
import WaistHistoryList from '@/components/WaistHistoryList'
import WeightPredictions from '@/components/WeightPredictions'
import GoalSetting from '@/components/GoalSetting'
import Achievements from '@/components/Achievements'
import WeightChangesWidget from '@/components/WeightChangesWidget'
import WaistChangesWidget from '@/components/WaistChangesWidget'
import DailyRateExplorer from '@/components/DailyRateExplorer'
import BMIVisualization from '@/components/BMIVisualization'
import WHtRVisualization from '@/components/WHtRVisualization'
import { WeightProvider } from '@/contexts/WeightContext'
import { Ruler } from 'lucide-react'

const VIEW_MODE_KEY = 'weight-ui-view-mode'
const ACTIVE_TAB_KEY = 'weight-ui-active-tab'
const TAB_KEYS = ['track', 'insights', 'charts', 'history']

export default function WeightTracker({
    weightListWithIds,
    chartData,
    predictions,
    goals = [],
    achievements = [],
    currentStreak = 0,
    motivationalMessage = '',
    weightChanges = null,
    waistMeasurements = [],
    waistChartData = [],
    waistChanges = null
}) {
    const [viewMode, setViewMode] = useState('dashboard')
    const [activeTab, setActiveTab] = useState('track')

    // State for waist measurement form
    const [waistCm, setWaistCm] = useState('')
    const [waistDate, setWaistDate] = useState(new Date().toISOString().split('T')[0])

    useEffect(() => {
        const storedViewMode = localStorage.getItem(VIEW_MODE_KEY)
        if (storedViewMode === 'dashboard' || storedViewMode === 'tabbed') {
            setViewMode(storedViewMode)
        }

        const storedActiveTab = localStorage.getItem(ACTIVE_TAB_KEY)
        if (storedActiveTab && TAB_KEYS.includes(storedActiveTab)) {
            setActiveTab(storedActiveTab)
        }
    }, [])

    useEffect(() => {
        localStorage.setItem(VIEW_MODE_KEY, viewMode)
    }, [viewMode])

    useEffect(() => {
        localStorage.setItem(ACTIVE_TAB_KEY, activeTab)
    }, [activeTab])

    // Handle waist measurement submission
    const handleWaistSubmit = (e) => {
        e.preventDefault()

        if (!waistCm || !waistDate) return

        router.post('/waist', {
            waist_cm: parseFloat(waistCm),
            date: waistDate
        }, {
            onSuccess: () => {
                setWaistCm('')
                setWaistDate(new Date().toISOString().split('T')[0])
            }
        })
    }

    // Calculate goal BMI from first active goal (height hardcoded to 175cm)
    const heightInMeters = 1.75
    const firstGoal = goals[0]
    const goalBMI = firstGoal?.target_weight
        ? Math.round((firstGoal.target_weight / (heightInMeters * heightInMeters)) * 10) / 10
        : null

    const selectedTabMeta = {
        track: {
            title: 'Track',
            description: 'Log new entries and manage your goals.'
        },
        insights: {
            title: 'Insights',
            description: 'Review predictions, trends, and progress.'
        },
        charts: {
            title: 'Charts',
            description: 'Visualize weight and waist progression.'
        },
        history: {
            title: 'History',
            description: 'Browse and clean up past measurements.'
        }
    }[activeTab]

    const renderWaistMeasurementForm = () => (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Ruler className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    Add Waist Measurement
                </CardTitle>
            </CardHeader>
            <CardContent>
                <form onSubmit={handleWaistSubmit} className="space-y-4">
                    <div>
                        <label htmlFor="waist" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                            Waist Circumference (cm)
                        </label>
                        <Input
                            id="waist"
                            type="number"
                            placeholder="Enter waist size in cm"
                            value={waistCm}
                            onChange={(e) => setWaistCm(e.target.value)}
                            min="40"
                            max="200"
                            step="0.1"
                        />
                        <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            Measure at your natural waistline (between ribs and hips)
                        </p>
                    </div>

                    <div>
                        <label htmlFor="waistDate" className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                            Date
                        </label>
                        <Input
                            id="waistDate"
                            type="date"
                            value={waistDate}
                            onChange={(e) => setWaistDate(e.target.value)}
                        />
                    </div>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={!waistCm}
                    >
                        Add Measurement
                    </Button>
                </form>
            </CardContent>
        </Card>
    )

    const renderWeightChart = () => (
        <Card>
            <CardHeader>
                <CardTitle>Weight Progress</CardTitle>
            </CardHeader>
            <CardContent>
                <WeightChart data={chartData || []} />
            </CardContent>
        </Card>
    )

    const renderWaistChart = () => (
        waistChartData && waistChartData.length > 0 && (
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <Ruler className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        Waist Progress
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <WaistChart data={waistChartData || []} />
                </CardContent>
            </Card>
        )
    )

    const renderHistory = ({ stacked = false } = {}) => (
        <div className={stacked ? 'grid grid-cols-1 gap-8' : 'grid grid-cols-1 gap-8 xl:grid-cols-2'}>
            <WeightHistoryList weightListWithIds={weightListWithIds} />
            <WaistHistoryList waistMeasurements={waistMeasurements} />
        </div>
    )

    const renderDashboardView = () => (
        <div className="space-y-8">
            <WeightChangesWidget />

            <div className="grid grid-cols-1 xl:grid-cols-2 gap-8">
                <div className="xl:col-span-1 space-y-6">
                    <WeightEntryForm />
                    {renderWaistMeasurementForm()}
                    <Achievements
                        achievements={achievements}
                        currentStreak={currentStreak}
                        motivationalMessage={motivationalMessage}
                    />
                </div>

                <div className="xl:col-span-1 space-y-6">
                    <GoalSetting goals={goals} />
                    <WeightPredictions predictions={predictions} />
                    <DailyRateExplorer chartData={chartData} />
                    <BMIVisualization
                        currentBMI={weightChanges?.current_bmi}
                        currentWeight={weightChanges?.current_weight}
                        startingBMI={weightChanges?.starting_bmi}
                        startingWeight={weightChanges?.starting_weight}
                        startingDate={weightChanges?.starting_date}
                        goalBMI={goalBMI}
                        healthyBMIDate={predictions?.healthyBMIDate}
                        healthyBMIWeight={predictions?.healthyBMIWeight}
                        chartData={chartData}
                        predictions={predictions}
                    />
                    <WHtRVisualization
                        waistCm={waistChanges?.current_waist}
                        waistChartData={waistChartData}
                    />
                </div>
            </div>

            {waistChanges && <WaistChangesWidget waistChanges={waistChanges} />}
            {renderWeightChart()}
            {renderWaistChart()}
            {renderHistory()}
        </div>
    )

    const renderTabButton = (tabKey, label) => (
        <Button
            type="button"
            role="tab"
            key={tabKey}
            aria-selected={activeTab === tabKey}
            aria-controls={`${tabKey}-panel`}
            id={`${tabKey}-tab`}
            size="sm"
            variant="ghost"
            className={activeTab === tabKey
                ? 'h-10 w-full rounded-xl border border-blue-300 bg-blue-600 px-3 text-left text-sm font-semibold text-white shadow-sm hover:bg-blue-500 dark:border-blue-700 dark:bg-blue-500 dark:text-slate-950 dark:hover:bg-blue-400'
                : 'h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-left text-sm font-semibold text-slate-700 shadow-sm hover:border-blue-300 hover:bg-blue-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-blue-700 dark:hover:bg-slate-800'
            }
            onClick={() => setActiveTab(tabKey)}
        >
            {label}
        </Button>
    )

    const renderTabbedView = () => (
        <div className="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(320px,360px)_minmax(0,1fr)]">
            <aside className="w-full space-y-4 xl:sticky xl:top-6 xl:self-start xl:justify-self-center">
                <Card className="mx-auto w-full max-w-[340px]">
                    <CardHeader>
                        <CardTitle className="text-base text-center">Overview</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
                            <div className="text-center text-xs text-slate-500 dark:text-slate-400">Current Weight</div>
                            <div className="text-center text-lg font-semibold text-slate-900 dark:text-slate-100">{weightChanges?.current_weight ?? 'N/A'} kg</div>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
                            <div className="text-center text-xs text-slate-500 dark:text-slate-400">Current BMI</div>
                            <div className="text-center text-lg font-semibold text-slate-900 dark:text-slate-100">{weightChanges?.current_bmi ?? 'N/A'}</div>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
                            <div className="text-center text-xs text-slate-500 dark:text-slate-400">Tracking Days</div>
                            <div className="text-center text-lg font-semibold text-slate-900 dark:text-slate-100">{weightChanges?.unique_days ?? 0}</div>
                        </div>
                        <div className="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-700 dark:bg-slate-900/70">
                            <div className="text-center text-xs text-slate-500 dark:text-slate-400">Current Streak</div>
                            <div className="text-center text-lg font-semibold text-slate-900 dark:text-slate-100">{currentStreak} day{currentStreak === 1 ? '' : 's'}</div>
                        </div>
                    </CardContent>
                </Card>

                <div className="mx-auto w-full max-w-[340px]">
                    <WeightChangesWidget compact />
                </div>
                {waistChanges && (
                    <div className="mx-auto w-full max-w-[340px]">
                        <WaistChangesWidget waistChanges={waistChanges} compact />
                    </div>
                )}

                <Card className="mx-auto w-full max-w-[340px]">
                    <CardContent className="pt-6">
                        <p className="text-center text-sm font-medium text-slate-700 dark:text-slate-200">{selectedTabMeta.title}</p>
                        <p className="mt-1 text-center text-xs text-slate-500 dark:text-slate-400">{selectedTabMeta.description}</p>
                    </CardContent>
                </Card>
            </aside>

            <div className="space-y-6 min-w-0">
                <div role="tablist" aria-label="Tracker tabs" className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    {renderTabButton('track', 'Track')}
                    {renderTabButton('insights', 'Insights')}
                    {renderTabButton('charts', 'Charts')}
                    {renderTabButton('history', 'History')}
                </div>

                {activeTab === 'track' && (
                    <section id="track-panel" role="tabpanel" aria-labelledby="track-tab" className="grid grid-cols-1 gap-8 xl:grid-cols-2">
                        <div className="space-y-6">
                            <WeightEntryForm />
                            {renderWaistMeasurementForm()}
                        </div>
                        <div className="space-y-6">
                            <GoalSetting goals={goals} />
                        </div>
                    </section>
                )}

                {activeTab === 'insights' && (
                    <section id="insights-panel" role="tabpanel" aria-labelledby="insights-tab" className="grid grid-cols-1 gap-8 xl:grid-cols-2">
                        <div className="space-y-6">
                            <WeightPredictions predictions={predictions} />
                            <DailyRateExplorer chartData={chartData} />
                            <BMIVisualization
                                currentBMI={weightChanges?.current_bmi}
                                currentWeight={weightChanges?.current_weight}
                                startingBMI={weightChanges?.starting_bmi}
                                startingWeight={weightChanges?.starting_weight}
                                startingDate={weightChanges?.starting_date}
                                goalBMI={goalBMI}
                                healthyBMIDate={predictions?.healthyBMIDate}
                                healthyBMIWeight={predictions?.healthyBMIWeight}
                                chartData={chartData}
                                predictions={predictions}
                            />
                        </div>
                        <div className="space-y-6">
                            <WHtRVisualization
                                waistCm={waistChanges?.current_waist}
                                waistChartData={waistChartData}
                            />
                            <Achievements
                                achievements={achievements}
                                currentStreak={currentStreak}
                                motivationalMessage={motivationalMessage}
                            />
                        </div>
                    </section>
                )}

                {activeTab === 'charts' && (
                    <section id="charts-panel" role="tabpanel" aria-labelledby="charts-tab" className="space-y-8">
                        {renderWeightChart()}
                        {renderWaistChart() || (
                            <Card>
                                <CardContent className="pt-6">
                                    <p className="text-sm text-slate-600 dark:text-slate-300">Add waist measurements to unlock the waist chart.</p>
                                </CardContent>
                            </Card>
                        )}
                    </section>
                )}

                {activeTab === 'history' && (
                    <section id="history-panel" role="tabpanel" aria-labelledby="history-tab">
                        {renderHistory({ stacked: true })}
                    </section>
                )}
            </div>
        </div>
    )

    return (
        <WeightProvider weightChanges={weightChanges}>
            <div className="min-h-screen">
                <div className="max-w-7xl mx-auto px-4 py-8 md:py-10">
                    <div className="text-center mb-8">
                        <h1 className="text-3xl font-bold tracking-tight text-slate-950 dark:text-slate-50">Weight Tracker</h1>
                        <p className="mt-2 text-slate-600 dark:text-slate-300">Track your weight loss journey</p>
                    </div>

                    <div className="mb-6 flex justify-center">
                        <div className="inline-flex gap-1 rounded-lg border border-slate-300 bg-white/90 p-1 dark:border-slate-700 dark:bg-slate-900/80">
                            <Button
                                type="button"
                                size="sm"
                                variant={viewMode === 'dashboard' ? 'default' : 'ghost'}
                                className={viewMode === 'dashboard' ? '' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-100'}
                                onClick={() => setViewMode('dashboard')}
                            >
                                Dashboard
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant={viewMode === 'tabbed' ? 'default' : 'ghost'}
                                className={viewMode === 'tabbed' ? '' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-100'}
                                onClick={() => setViewMode('tabbed')}
                            >
                                Tabbed
                            </Button>
                        </div>
                    </div>

                    {viewMode === 'dashboard' ? renderDashboardView() : renderTabbedView()}
                </div>
            </div>
        </WeightProvider>
    )
}
