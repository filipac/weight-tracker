import { router } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Ruler } from 'lucide-react'

export default function WaistHistoryList({ waistMeasurements }) {
    const handleDelete = (id) => {
        if (confirm('Are you sure you want to delete this waist measurement?')) {
            router.delete(`/waist/${id}`)
        }
    }

    return (
        <Card className="flex-1 min-h-0 flex flex-col">
            <CardHeader className="flex-shrink-0">
                <CardTitle className="flex items-center gap-2">
                    <Ruler className="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    Waist Measurement History
                </CardTitle>
            </CardHeader>
            <CardContent className="flex-1 min-h-0 flex flex-col">
                <div className="space-y-2 flex-1 overflow-y-auto max-h-96">
                    {waistMeasurements && waistMeasurements.length > 0 ? (
                        waistMeasurements.map((measurement, index) => (
                            <div
                                key={`${measurement.id ?? 'waist'}-${measurement.date ?? 'no-date'}-${index}`}
                                className="flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-950/30"
                            >
                                <div className="flex-1">
                                    <div className="font-medium text-slate-900 dark:text-slate-100">
                                        {measurement.waist_cm} cm
                                    </div>
                                    <div className="text-sm text-slate-600 dark:text-slate-300">
                                        {measurement.date}
                                    </div>
                                </div>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onClick={() => handleDelete(measurement.id)}
                                    className="ml-2 select-none"
                                >
                                    Delete
                                </Button>
                            </div>
                        ))
                    ) : (
                        <p className="py-4 text-center text-slate-500 dark:text-slate-400">No waist measurements yet</p>
                    )}
                </div>
            </CardContent>
        </Card>
    )
}
