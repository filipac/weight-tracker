import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Trophy, Award, Flame, Target, Calendar, Star } from 'lucide-react'

export default function Achievements({ achievements = [], currentStreak = 0, motivationalMessage = '' }) {
    const getAchievementIcon = (type) => {
        switch (type) {
            case 'streak': return <Flame className="h-5 w-5 text-orange-500 dark:text-orange-400" />
            case 'milestone': return <Award className="h-5 w-5 text-purple-500 dark:text-purple-400" />
            case 'goal_achieved': return <Target className="h-5 w-5 text-green-500 dark:text-green-400" />
            default: return <Trophy className="h-5 w-5 text-yellow-500 dark:text-yellow-400" />
        }
    }

    const getAchievementBg = (type) => {
        switch (type) {
            case 'streak': return 'bg-orange-50 dark:bg-orange-900/20 border-orange-200 dark:border-orange-800'
            case 'milestone': return 'bg-purple-50 dark:bg-purple-900/20 border-purple-200 dark:border-purple-800'
            case 'goal_achieved': return 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800'
            default: return 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800'
        }
    }

    const formatDate = (dateString) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        })
    }

    // Group achievements by type for better organization
    const groupedAchievements = achievements.reduce((groups, achievement) => {
        const type = achievement.type
        if (!groups[type]) {
            groups[type] = []
        }
        groups[type].push(achievement)
        return groups
    }, {})

    return (
        <Card className="flex-shrink-0">
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Trophy className="h-5 w-5 text-yellow-600 dark:text-yellow-400" />
                    Achievements & Progress
                </CardTitle>
            </CardHeader>
            <CardContent>
                {/* Motivational Message */}
                {motivationalMessage && (
                    <div className="mb-6 p-4 bg-gradient-to-r from-purple-50 to-blue-50 dark:from-purple-900/30 dark:to-blue-900/30 rounded-lg border border-purple-200 dark:border-purple-700">
                        <p className="text-purple-800 dark:text-purple-300 font-medium text-center">
                            {motivationalMessage}
                        </p>
                    </div>
                )}

                {/* Current Streak Display */}
                <div className="mb-6 p-4 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-800">
                    <div className="flex items-center gap-3">
                        <Flame className="h-6 w-6 text-orange-500 dark:text-orange-400" />
                        <div>
                            <h3 className="font-semibold text-orange-800 dark:text-orange-300">Current Logging Streak</h3>
                            <p className="text-orange-600 dark:text-orange-400">
                                {currentStreak > 0
                                    ? `${currentStreak} consecutive ${currentStreak === 1 ? 'day' : 'days'}`
                                    : 'No active streak - log today to start!'
                                }
                            </p>
                        </div>
                    </div>
                </div>

                {/* Achievements */}
                {achievements.length === 0 ? (
                    <div className="text-center py-8">
                        <Star className="h-12 w-12 text-gray-400 dark:text-gray-500 mx-auto mb-3" />
                        <p className="text-gray-600 dark:text-gray-400 mb-2">No achievements yet</p>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Keep logging your weight to earn your first achievement!
                        </p>
                    </div>
                ) : (
                    <div className="space-y-6">
                        {/* Recent Achievements */}
                        {achievements.length > 0 && (
                            <div>
                                <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">Recent Achievements</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    {achievements.slice(0, 6).map(achievement => (
                                        <div key={achievement.id} className={`p-4 rounded-lg border ${getAchievementBg(achievement.type)}`}>
                                            <div className="flex items-start gap-3">
                                                <div className="flex-shrink-0 mt-1">
                                                    {getAchievementIcon(achievement.type)}
                                                </div>
                                                <div className="flex-1">
                                                    <h4 className="font-semibold text-gray-800 dark:text-gray-200 mb-1">
                                                        {achievement.title}
                                                    </h4>
                                                    <p className="text-gray-600 dark:text-gray-400 text-sm mb-2">
                                                        {achievement.description}
                                                    </p>
                                                    <div className="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                                                        <Calendar className="h-3 w-3" />
                                                        <span>Earned {formatDate(achievement.earned_date)}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Achievement Summary by Type */}
                        {Object.keys(groupedAchievements).length > 0 && (
                            <div>
                                <h3 className="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">Achievement Summary</h3>
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    {groupedAchievements.streak && (
                                        <div className="bg-orange-50 dark:bg-orange-900/20 p-4 rounded-lg border border-orange-200 dark:border-orange-800">
                                            <div className="flex items-center gap-2 mb-2">
                                                <Flame className="h-5 w-5 text-orange-500 dark:text-orange-400" />
                                                <span className="font-semibold text-orange-800 dark:text-orange-300">Streak Badges</span>
                                            </div>
                                            <p className="text-2xl font-bold text-orange-600 dark:text-orange-400">
                                                {groupedAchievements.streak.length}
                                            </p>
                                            <p className="text-sm text-orange-600 dark:text-orange-400">
                                                Longest: {Math.max(...groupedAchievements.streak.map(a => a.value))} days
                                            </p>
                                        </div>
                                    )}

                                    {groupedAchievements.milestone && (
                                        <div className="bg-purple-50 dark:bg-purple-900/20 p-4 rounded-lg border border-purple-200 dark:border-purple-800">
                                            <div className="flex items-center gap-2 mb-2">
                                                <Award className="h-5 w-5 text-purple-500 dark:text-purple-400" />
                                                <span className="font-semibold text-purple-800 dark:text-purple-300">Weight Loss</span>
                                            </div>
                                            <p className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                                {groupedAchievements.milestone.length}
                                            </p>
                                            <p className="text-sm text-purple-600 dark:text-purple-400">
                                                Max: {Math.max(...groupedAchievements.milestone.map(a => a.value))}kg lost
                                            </p>
                                        </div>
                                    )}

                                    {groupedAchievements.goal_achieved && (
                                        <div className="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                                            <div className="flex items-center gap-2 mb-2">
                                                <Target className="h-5 w-5 text-green-500 dark:text-green-400" />
                                                <span className="font-semibold text-green-800 dark:text-green-300">Goals Met</span>
                                            </div>
                                            <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                                {groupedAchievements.goal_achieved.length}
                                            </p>
                                            <p className="text-sm text-green-600 dark:text-green-400">
                                                Targets achieved
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    )
}
