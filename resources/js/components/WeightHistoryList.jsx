import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

export default function WeightHistoryList({ weightListWithIds }) {
    const handleDelete = (id) => {
        if (confirm('Are you sure you want to delete this weight entry?')) {
            router.delete(`/weight/${id}`)
        }
    }

    const handleSync = () => {
        if (confirm('Sync new entries from Notes.app? This will add any manually entered weight entries.')) {
            router.post('/weight/sync')
        }
    }

    return (
        <Card className="flex-1 min-h-0 flex flex-col">
            <CardHeader className="flex-shrink-0">
                <div className="flex items-center justify-between">
                    <CardTitle>Weight History</CardTitle>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={handleSync}
                        className="ml-2"
                    >
                        Sync from Notes
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="flex-1 min-h-0 flex flex-col">
                <div className="space-y-2 flex-1 overflow-y-auto max-h-96">
                    {weightListWithIds && weightListWithIds.length > 0 ? (
                        weightListWithIds.map((item, index) => (
                            <div
                                key={item.id || index}
                                className={`flex items-center justify-between p-3 rounded border ${item.type === 'summary' ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800' : 'bg-gray-100 dark:bg-gray-800 border-gray-200 dark:border-gray-700'
                                    }`}
                            >
                                <div className={`font-mono text-sm ${item.type === 'summary' ? 'text-blue-800 dark:text-blue-300 font-semibold' : 'text-gray-900 dark:text-gray-100'
                                    }`}>
                                    {item.text}
                                </div>
                                {item.id && (
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleDelete(item.id)}
                                        className="ml-2"
                                    >
                                        Delete
                                    </Button>
                                )}
                            </div>
                        ))
                    ) : (
                        <p className="text-gray-500 dark:text-gray-400 text-center py-4">No weight entries yet</p>
                    )}
                </div>
            </CardContent>
        </Card>
    )
}
