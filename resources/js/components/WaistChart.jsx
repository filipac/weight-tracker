import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer } from 'recharts'

export default function WaistChart({ data }) {
    // Filter and prepare data
    const chartData = data
        .filter(item => item.waist && item.date)
        .map(item => {
            const waist = parseFloat(item.waist)
            return {
                date: item.date,
                waist: isNaN(waist) ? 0 : waist,
                displayDate: new Date(item.date).toLocaleDateString('en-US', {
                    month: 'short',
                    day: 'numeric'
                })
            }
        })
        .filter(item => item.waist > 0 && item.waist < 300)
        .sort((a, b) => new Date(a.date) - new Date(b.date))

    if (chartData.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center text-slate-500 dark:text-slate-400">
                No waist measurement data available for chart
            </div>
        )
    }

    const formatXAxisTick = (dateValue) => {
        const date = new Date(dateValue)
        if (Number.isNaN(date.getTime())) return dateValue

        return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
    }

    return (
        <div className="w-full">
            <div className="h-64">
                <ResponsiveContainer width="100%" height="100%">
                    <LineChart
                        data={chartData}
                        margin={{ top: 5, right: 30, left: 20, bottom: 5 }}
                    >
                        <CartesianGrid strokeDasharray="3 3" stroke="var(--app-border)" strokeOpacity={0.4} />
                        <XAxis
                            dataKey="date"
                            tickFormatter={formatXAxisTick}
                            tick={{ fontSize: 12, fill: 'currentColor' }}
                            className="text-slate-700 dark:text-slate-300"
                            angle={-45}
                            textAnchor="end"
                            height={60}
                        />
                        <YAxis
                            domain={['dataMin - 5', 'dataMax + 5']}
                            tick={{ fontSize: 12, fill: 'currentColor' }}
                            className="text-slate-700 dark:text-slate-300"
                            label={{
                                value: 'Waist (cm)',
                                angle: -90,
                                position: 'insideLeft',
                                style: { textAnchor: 'middle', fill: 'currentColor' }
                            }}
                        />
                        <Tooltip
                            contentStyle={{
                                backgroundColor: 'var(--app-bg-elevated)',
                                borderColor: 'var(--app-border)',
                                borderRadius: '0.5rem',
                                color: 'var(--app-text)',
                            }}
                            labelStyle={{ color: 'var(--app-text)' }}
                            itemStyle={{ color: 'var(--app-text)' }}
                            formatter={(value) => [`${value.toFixed(1)} cm`, 'Waist']}
                            labelFormatter={(label, payload) => {
                                if (payload && payload[0]) {
                                    const data = payload[0].payload
                                    return new Date(data.date).toLocaleDateString('en-US', {
                                        weekday: 'long',
                                        year: 'numeric',
                                        month: 'long',
                                        day: 'numeric'
                                    })
                                }
                                return label
                            }}
                        />
                        <Line
                            type="monotone"
                            dataKey="waist"
                            stroke="#9333ea"
                            strokeWidth={2}
                            dot={{ fill: '#9333ea', strokeWidth: 2, r: 4 }}
                            activeDot={{ r: 6, fill: '#7e22ce' }}
                        />
                    </LineChart>
                </ResponsiveContainer>
            </div>
        </div>
    )
}
