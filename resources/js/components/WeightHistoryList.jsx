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
                                key={`${item.id ?? 'weight-item'}-${index}`}
                                className={`flex items-center justify-between rounded-lg border p-3 ${item.type === 'summary' ? 'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-950/30' : 'border-slate-200 bg-slate-100/80 dark:border-slate-700 dark:bg-slate-900/60'
                                    }`}
                            >
                                <div className={`font-mono text-sm ${item.type === 'summary' ? 'font-semibold text-blue-900 dark:text-blue-300' : 'text-slate-900 dark:text-slate-100'
                                    }`}>
                                    {item.text}
                                </div>
                                {item.id && (
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleDelete(item.id)}
                                        className="ml-2 select-none"
                                    >
                                        Delete
                                    </Button>
                                )}
                            </div>
                        ))
                    ) : (
                        <p className="py-4 text-center text-slate-500 dark:text-slate-400">No weight entries yet</p>
                    )}
                </div>
            </CardContent>
        </Card>
    )
}
