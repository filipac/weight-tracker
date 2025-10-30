import { useState } from 'react'
import { router, usePage } from '@inertiajs/react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import axios from 'axios'

export default function WeightEntryForm() {
    const { flash, weightFromWithings, withingsConfigured } = usePage().props

    const [weight, setWeight] = useState(weightFromWithings?.weight || '')

    const [isLoading, setIsLoading] = useState(false)

    const [date, setDate] = useState(new Date().toISOString().split('T')[0])
    const [isLbs, setIsLbs] = useState(!weightFromWithings?.weight)

    const convertLbsToKg = () => {
        if (weight && isLbs) {
            const kgWeight = (parseFloat(weight) * 0.453592).toFixed(2)
            setWeight(kgWeight)
            setIsLbs(false)
        }
    }

    const handleSubmit = (e) => {
        e.preventDefault()

        if (!weight || !date) return

        router.post('/weight', {
            weight_kg: parseFloat(weight),
            date: date
        }, {
            onSuccess: () => {
                setWeight('')
                setDate(new Date().toISOString().split('T')[0])
                setIsLbs(true)
            }
        })
    }

    const handleGetFromWithings = () => {
        setIsLoading(true)
        axios.post('/weight/get-from-withings', {
            date: date
        }).then((response) => {
            let data = response.data
            setWeight(data.weight)
            setIsLbs(false)
            setIsLoading(false)
        }).catch((error) => {
            console.log(error)
            setIsLoading(false)
        })
    }

    return (
        <Card className="flex-shrink-0">
            <CardHeader>
                <CardTitle>Add New Weight Entry</CardTitle>
            </CardHeader>
            <CardContent>
                {flash.message && (
                    <div className="bg-yellow-100 dark:bg-yellow-900 border border-yellow-400 dark:border-yellow-600 text-yellow-700 dark:text-yellow-300 px-4 py-3 rounded relative mb-4" role="alert">
                        <span className="block sm:inline">{flash.message}</span>
                    </div>
                )}
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label htmlFor="weight" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Weight ({isLbs ? 'lbs' : 'kg'})
                        </label>
                        <div className="flex space-x-2">
                            <Input
                                id="weight"
                                type="number"
                                step="0.01"
                                value={weight}
                                onChange={(e) => setWeight(e.target.value)}
                                placeholder={`Enter weight in ${isLbs ? 'lbs' : 'kg'}`}
                                className="flex-1"
                            />
                            {isLbs && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={convertLbsToKg}
                                    className="whitespace-nowrap"
                                >
                                    Convert to kg
                                </Button>
                            )}
                        </div>
                        {withingsConfigured && (
                            <div className="flex items-center justify-center mt-4 space-x-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleGetFromWithings}
                                    disabled={isLoading}
                                >
                                    {isLoading && <Spinner />}
                                    {!isLoading && 'Get from Withings'}
                                </Button>
                            </div>
                        )}
                    </div>

                    <div>
                        <label htmlFor="date" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Date
                        </label>
                        <Input
                            id="date"
                            type="date"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                        />
                    </div>

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={isLbs || !weight}
                    >
                        Add Entry
                    </Button>
                    {isLbs && weight && (
                        <p className="text-sm text-amber-600 dark:text-amber-400 text-center">
                            Please convert to kg before submitting
                        </p>
                    )}
                </form>
            </CardContent>
        </Card>
    )
}

function Spinner() {
    return (
        <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-gray-900 dark:border-gray-100"></div>
    )
}
