import { useState } from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Target, Plus, Trash2, Edit3, Calendar, TrendingDown, TrendingUp, Minus } from 'lucide-react'
import { useForm } from '@inertiajs/react'

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
            case 'lose': return 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
            case 'gain': return 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'
            case 'maintain': return 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800'
            default: return 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700'
        }
    }

    return (
        <Card className="flex-shrink-0">
            <CardHeader>
                <CardTitle className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                        <Target className="h-5 w-5 text-purple-600 dark:text-purple-400" />
                        Weight Goals
                    </div>
                    {!isCreating && (
                        <Button
                            onClick={() => setIsCreating(true)}
                            size="sm"
                            className="bg-purple-600 hover:bg-purple-700"
                        >
                            <Plus className="h-4 w-4 mr-1" />
                            Add Goal
                        </Button>
                    )}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {/* Goal Creation/Edit Form */}
                {isCreating && (
                    <div className="mb-6 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
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
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
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
                                    <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        Optional: Set your journey's starting point
                                    </p>
                                </div>

                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                        Goal Type
                                    </label>
                                    <select
                                        value={data.goal_type}
                                        onChange={e => setData('goal_type', e.target.value)}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                    >
                                        <option value="lose">Lose Weight</option>
                                        <option value="gain">Gain Weight</option>
                                        <option value="maintain">Maintain Weight</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
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
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
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
                                <Button type="submit" disabled={processing} className="bg-purple-600 hover:bg-purple-700">
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
                        <Target className="h-12 w-12 text-gray-400 dark:text-gray-500 mx-auto mb-3" />
                        <p className="text-gray-600 dark:text-gray-400 mb-2">No weight goals set yet</p>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Create your first goal to start tracking your progress
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {goals.map(goal => (
                            <div key={goal.id} className={`p-4 rounded-lg border-2 ${getGoalColor(goal.goal_type)}`}>
                                <div className="flex items-start justify-between">
                                    <div className="flex-1">
                                        <div className="flex items-center gap-2 mb-2">
                                            {getGoalIcon(goal.goal_type)}
                                            <h3 className="font-semibold text-gray-900 dark:text-gray-100">
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
                                            <p className="text-gray-600 dark:text-gray-400 text-sm mb-2">{goal.description}</p>
                                        )}

                                        <div className="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                                            {goal.target_date && (
                                                <div className="flex items-center gap-1">
                                                    <Calendar className="h-4 w-4" />
                                                    <span>Target: {goal.target_date}</span>
                                                    {goal.days_to_target !== null && (
                                                        <span className={`ml-1 ${goal.days_to_target < 0 ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400'}`}>
                                                            ({goal.days_to_target < 0 ? 'overdue' : `${goal.days_to_target} days`})
                                                        </span>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        {/* Progress Bar */}
                                        <div className="mt-3">
                                            <div className="flex justify-between items-center text-sm mb-1">
                                                <span className="text-gray-600 dark:text-gray-400">Progress</span>
                                                <span className="font-medium">{goal.progress}%</span>
                                            </div>
                                            <div className="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                <div
                                                    className="bg-purple-600 dark:bg-purple-500 h-2 rounded-full transition-all duration-300"
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
                                            className="p-2 text-red-600 hover:text-red-700 hover:bg-red-50"
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
