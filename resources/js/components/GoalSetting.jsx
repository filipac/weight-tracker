import { useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Target, Plus, Trash2, Edit3, Calendar, TrendingDown, TrendingUp, Minus, RefreshCw } from 'lucide-react'
import { useForm, router } from '@inertiajs/react'

export default function GoalSetting({ goals = [] }) {
    const [isCreating, setIsCreating] = useState(false)
    const [editingId, setEditingId] = useState(null)

    const { data, setData, post, put, delete: destroy, processing, reset } = useForm({
        target_weight: '',
        target_date: '',
        goal_type: 'lose',
        description: '',
        starting_weight: ''
    })

    const handleSubmit = (e) => {
        e.preventDefault()

        if (editingId) {
            put(`/goals/${editingId}`, {
                onSuccess: () => {
                    reset()
                    setEditingId(null)
                }
            })
        } else {
            post('/goals', {
                onSuccess: () => {
                    reset()
                    setIsCreating(false)
                }
            })
        }
    }

    const handleEdit = (goal) => {
        setData({
            target_weight: goal.target_weight,
            target_date: goal.raw_target_date || '',
            goal_type: goal.goal_type,
            description: goal.description || '',
            starting_weight: goal.starting_weight || ''
        })
        setEditingId(goal.id)
        setIsCreating(true)
    }

    const handleDelete = (goalId) => {
        if (confirm('Are you sure you want to delete this goal?')) {
            destroy(`/goals/${goalId}`)
        }
    }

    const cancelEdit = () => {
        reset()
        setEditingId(null)
        setIsCreating(false)
    }

    const handleRecalculate = () => {
        router.post('/goals/recalculate')
    }

    const getGoalIcon = (goalType) => {
        switch (goalType) {
            case 'lose': return <TrendingDown className="h-4 w-4 text-green-600 dark:text-green-400" />
            case 'gain': return <TrendingUp className="h-4 w-4 text-blue-600 dark:text-blue-400" />
            case 'maintain': return <Minus className="h-4 w-4 text-yellow-600 dark:text-yellow-400" />
            default: return <Target className="h-4 w-4" />
        }
    }

    const getGoalColor = (goalType) => {
        switch (goalType) {
            case 'lose': return 'bg-green-50 dark:bg-green-950/30 border-green-200 dark:border-green-800'
            case 'gain': return 'bg-blue-50 dark:bg-blue-950/30 border-blue-200 dark:border-blue-800'
            case 'maintain': return 'bg-yellow-50 dark:bg-yellow-950/30 border-yellow-200 dark:border-yellow-800'
            default: return 'bg-slate-50 dark:bg-slate-900/60 border-slate-200 dark:border-slate-700'
        }
    }

    return (
        <Card className="flex-shrink-0">
            <CardHeader>
                <CardTitle className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Target className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                        Weight Goals
                    </div>
                    {!isCreating && (
                        <div className="flex gap-2">
                            {goals.length > 0 && (
                                <Button
                                    onClick={handleRecalculate}
                                    size="sm"
                                    variant="outline"
                                    title="Recalculate progress for all goals"
                                >
                                    <RefreshCw className="h-4 w-4 mr-1" />
                                    Recalculate
                                </Button>
                            )}
                            <Button
                                onClick={() => setIsCreating(true)}
                                size="sm"
                            >
                                <Plus className="h-4 w-4 mr-1" />
                                Add Goal
                            </Button>
                        </div>
                    )}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {/* Goal Creation/Edit Form */}
                {isCreating && (
                    <div className="mb-6 rounded-lg border-2 border-dashed border-slate-300 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/60">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Target Weight (kg)
                                    </label>
                                    <Input
                                        type="number"
                                        step="0.1"
                                        min="30"
                                        max="300"
                                        value={data.target_weight}
                                        onChange={e => setData('target_weight', e.target.value)}
                                        placeholder="e.g., 80.0"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Starting Weight (kg)
                                    </label>
                                    <Input
                                        type="number"
                                        step="0.1"
                                        min="30"
                                        max="300"
                                        value={data.starting_weight}
                                        onChange={e => setData('starting_weight', e.target.value)}
                                        placeholder="Leave empty for current weight"
                                    />
                                    <p className="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        Optional: Set your journey's starting point
                                    </p>
                                </div>

                                <div>
                                    <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                        Goal Type
                                    </label>
                                    <select
                                        value={data.goal_type}
                                        onChange={e => setData('goal_type', e.target.value)}
                                        className="w-full rounded-lg border border-slate-300/90 bg-white px-3 py-2 text-sm text-slate-900 shadow-xs outline-none transition-[border-color,box-shadow] focus-visible:border-primary/80 focus-visible:ring-[3px] focus-visible:ring-primary/25 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:focus-visible:border-primary/70 dark:focus-visible:ring-primary/35"
                                    >
                                        <option value="lose">Lose Weight</option>
                                        <option value="gain">Gain Weight</option>
                                        <option value="maintain">Maintain Weight</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Target Date (Optional)
                                </label>
                                <Input
                                    type="date"
                                    value={data.target_date}
                                    onChange={e => setData('target_date', e.target.value)}
                                    min={new Date().toISOString().split('T')[0]}
                                />
                            </div>

                            <div>
                                <label className="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">
                                    Description (Optional)
                                </label>
                                <Input
                                    type="text"
                                    value={data.description}
                                    onChange={e => setData('description', e.target.value)}
                                    placeholder="e.g., Summer body goal, Health improvement..."
                                    maxLength="500"
                                />
                            </div>

                            <div className="flex gap-2">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : editingId ? 'Update Goal' : 'Create Goal'}
                                </Button>
                                <Button type="button" onClick={cancelEdit} variant="outline">
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                {/* Goals List */}
                {goals.length === 0 ? (
                    <div className="text-center py-8">
                        <Target className="mx-auto mb-3 h-12 w-12 text-slate-400 dark:text-slate-500" />
                        <p className="mb-2 text-slate-600 dark:text-slate-300">No weight goals set yet</p>
                        <p className="text-sm text-slate-500 dark:text-slate-400">
                            Create your first goal to start tracking your progress
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {goals.map((goal, index) => (
                            <div key={`${goal.id ?? 'goal'}-${goal.target_date ?? 'no-date'}-${index}`} className={`p-4 rounded-lg border-2 ${getGoalColor(goal.goal_type)}`}>
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2 mb-2">
                                            {getGoalIcon(goal.goal_type)}
                                            <h3 className="font-semibold text-slate-900 dark:text-slate-100">
                                                {goal.goal_type === 'lose' && 'Get down to '}
                                                {goal.goal_type === 'gain' && 'Gain up to '}
                                                {goal.goal_type === 'maintain' && 'Maintain at '}
                                                {goal.target_weight} kg
                                            </h3>
                                            {goal.is_achieved && (
                                                <span className="px-2 py-1 bg-green-100 dark:bg-green-900/40 text-green-800 dark:text-green-300 text-xs font-medium rounded-full">
                                                    Achieved!
                                                </span>
                                            )}
                                        </div>

                                        {goal.description && (
                                            <p className="mb-2 text-sm text-slate-600 dark:text-slate-300">{goal.description}</p>
                                        )}

                                        <div className="flex items-center gap-4 text-sm text-slate-600 dark:text-slate-300">
                                            {goal.target_date && (
                                                <div className="flex items-center gap-1">
                                                    <Calendar className="h-4 w-4" />
                                                    <span>Target: {goal.target_date}</span>
                                                    {goal.days_to_target !== null && (
                                                        <span className={`ml-1 ${goal.days_to_target < 0 ? 'text-red-600 dark:text-red-400' : 'text-blue-700 dark:text-blue-300'}`}>
                                                            ({goal.days_to_target < 0 ? 'overdue' : `${goal.days_to_target} days`})
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        {/* Progress Bar */}
                                        <div className="mt-3">
                                            <div className="flex justify-between items-center text-sm mb-1">
                                                <span className="text-slate-600 dark:text-slate-300">Progress</span>
                                                <span className="font-medium">{goal.progress}%</span>
                                            </div>
                                            <div className="h-2 w-full rounded-full bg-slate-200 dark:bg-slate-700">
                                                <div
                                                    className="h-2 rounded-full bg-blue-600 transition-all duration-300 dark:bg-blue-500"
                                                    style={{ width: `${Math.min(100, Math.max(0, goal.progress))}%` }}
                                                ></div>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex gap-1 ml-4">
                                        <Button
                                            onClick={() => handleEdit(goal)}
                                            size="sm"
                                            variant="outline"
                                            className="p-2"
                                        >
                                            <Edit3 className="h-4 w-4" />
                                        </Button>
                                        <Button
                                            onClick={() => handleDelete(goal.id)}
                                            size="sm"
                                            variant="outline"
                                            className="p-2 text-red-600 hover:bg-red-50 hover:text-red-700 dark:hover:bg-red-950/30"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
        </Card>
    )
}
