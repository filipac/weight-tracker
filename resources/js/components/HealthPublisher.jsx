import { useEffect, useRef, useState } from 'react'
import axios from 'axios'
import { Button } from '@/components/ui/button'
import { runConcurrent } from '@/lib/runConcurrent'
import '../../css/health-publisher.css'

const pretty = value => new Intl.NumberFormat('en', { maximumFractionDigits: 2 }).format(value)
const errorMessage = error => error.response?.data?.message || 'The request could not complete. Please retry.'
const sources = { oura: 'Oura', withings: 'Withings', apple_health: 'Apple Health' }
const time = at => new Date(at).toLocaleString('en-GB', { timeZone: 'Europe/Bucharest' })

function Measurements({ section }) {
    return <>
        {!!section.metrics.length && <div className="hp-table-scroll"><table><thead><tr><th>Measurement</th><th>Value</th><th>Recorded at</th></tr></thead><tbody>{section.metrics.map((metric, i) => <tr key={`${metric.key}-${i}`}><th scope="row">{metric.label}</th><td>{metric.unit === 'timestamp' ? time(metric.value * 1000) : `${pretty(metric.value)} ${metric.unit}`}</td><td>{time(metric.at)}</td></tr>)}</tbody></table></div>}
        {section.series.map(series => <Series key={series.key} series={series} />)}
    </>
}

function Series({ series }) {
    const points = series.points
    const values = points.map(point => point.value)
    const min = Math.min(...values), max = Math.max(...values)
    const start = Date.parse(points[0].at), end = Date.parse(points[points.length - 1].at)
    const step = Math.max(1, Math.ceil(points.length / 600))
    const line = points.filter((_, i) => i % step === 0 || i === points.length - 1)
        .map(point => `${10 + 580 * (Date.parse(point.at) - start) / Math.max(1, end - start)},${100 - 85 * (point.value - min) / Math.max(1, max - min)}`).join(' ')
    return <figure className="hp-chart">
        <figcaption>{series.label} <small>{series.unit} · {points.length} samples</small></figcaption>
        <svg viewBox="0 0 600 115" role="img" aria-label={`${series.label}, minimum ${pretty(min)}, maximum ${pretty(max)} ${series.unit}`}><polyline points={line} fill="none" stroke="currentColor" strokeWidth="2" /></svg>
        <details><summary>Recorded values</summary><div className="hp-table-scroll"><table><thead><tr><th>Time</th><th>{series.unit}</th></tr></thead><tbody>{points.map((point, i) => <tr key={i}><td>{point.at}</td><td>{pretty(point.value)}</td></tr>)}</tbody></table></div></details>
    </figure>
}

function EntryDetails({ entry, workoutTypes }) {
    return <>{Object.entries(entry.providers).map(([source, section]) => <section className="hp-provider" key={source}>
        <h4>{sources[source]}</h4>
        <p className="hp-muted">Fetched {section.fetched_at}</p>
        <Measurements section={section} />
        {!!section.workouts?.length && <p>{section.workouts.length} workouts · Oura-origin sessions use these richer exported details; duplicate Oura API values are omitted.</p>}
        {section.workouts?.map(workout => <section className="hp-workout" key={`${workout.start}-${workout.type}`}><h5>{workoutTypes[workout.type]}</h5><p>{time(workout.start)} – {time(workout.end)} · {sources[workout.origin]}</p><Measurements section={workout} /></section>)}
    </section>)}</>
}

export default function HealthPublisher() {
    const [status, setStatus] = useState(null)
    const [destination, setDestination] = useState('local')
    const [snapshot, setSnapshot] = useState(null)
    const [preview, setPreview] = useState(null)
    const [tasks, setTasks] = useState({})
    const [selected, setSelected] = useState({})
    const [results, setResults] = useState({})
    const [busy, setBusy] = useState('')
    const [error, setError] = useState('')
    const [connectionResult, setConnectionResult] = useState(null)
    const generation = useRef(0)
    const snapshotRef = useRef(null)
    useEffect(() => {
        axios.get('/health/status').then(({ data }) => {
            setDestination(data.destination)
            setStatus(data)
        }).catch(e => setError(errorMessage(e)))
        return () => { generation.current++ }
    }, [])

    const reset = async nextDestination => {
        generation.current++
        const old = snapshotRef.current
        snapshotRef.current = null
        setSnapshot(null); setPreview(null); setTasks({}); setSelected({}); setResults({}); setError(''); setBusy('')
        if (nextDestination) { setDestination(nextDestination); setConnectionResult(null) }
        if (old) await axios.delete(`/health/previews/${old.id}`).catch(() => {})
    }

    const changeDestination = async nextDestination => {
        const resetting = reset(nextDestination)
        setBusy('saving')
        try {
            await axios.post('/health/destination', { destination: nextDestination })
            await resetting
        } catch (e) { setError('Could not remember this destination. ' + errorMessage(e)) }
        finally { setBusy('') }
    }

    const testConnection = async () => {
        setBusy('testing'); setConnectionResult(null)
        try {
            const { data } = await axios.post('/health/test-connection', { destination })
            setConnectionResult(data)
        } catch (e) { setConnectionResult({ connected: false, message: errorMessage(e) }) }
        finally { setBusy('') }
    }

    const fetchData = async () => {
        await reset()
        const run = ++generation.current
        setBusy('fetching')
        try {
            const { data } = await axios.post('/health/previews', { destination })
            if (run !== generation.current) return
            setStatus(previous => ({ ...previous, providers: { ...previous.providers, apple_health: {
                ...previous.providers.apple_health, folder: data.apple_health.folder || null,
                connected: !!data.apple_health.folder,
            } } }))
            snapshotRef.current = data; setSnapshot(data)
            setTasks(Object.fromEntries(data.tasks.map(task => [task, { state: 'waiting', message: 'Waiting' }])))
            // Small independent requests give visible progress without a queue worker.
            await runConcurrent(data.tasks, data.fetch_concurrency, async task => {
                setTasks(previous => ({ ...previous, [task]: { state: 'loading', message: 'Fetching…' } }))
                const response = await axios.post(`/health/previews/${data.id}/fetch/${task}`)
                if (run === generation.current) setTasks(previous => ({ ...previous, [task]: response.data }))
            }, () => run === generation.current)
            if (run !== generation.current) return
            setBusy('preparing')
            const prepared = await axios.post(`/health/previews/${data.id}/prepare`)
            if (run !== generation.current) return
            setPreview(prepared.data)
            setSelected(Object.fromEntries(Object.entries(prepared.data.entries).map(([key, item]) => [key, item.operation !== 'unchanged'])))
        } catch (e) { if (run === generation.current) setError(errorMessage(e)) }
        finally { if (run === generation.current) setBusy('') }
    }

    const publish = async () => {
        const run = generation.current
        setBusy('publishing'); setError('')
        for (const [key, checked] of Object.entries(selected)) {
            if (!checked || results[key]?.operation || results[key]?.stale || run !== generation.current) continue
            setResults(previous => ({ ...previous, [key]: { message: 'Publishing…' } }))
            try {
                const response = await axios.post(`/health/previews/${snapshot.id}/publish`, { destination, entry: key })
                if (run === generation.current) setResults(previous => ({ ...previous, [key]: response.data }))
            } catch (e) {
                if (run === generation.current) setResults(previous => ({ ...previous, [key]: { error: true, stale: e.response?.status === 409, message: errorMessage(e) } }))
            }
        }
        if (run === generation.current) setBusy('')
    }
    const pending = Object.keys(selected).filter(key => selected[key] && !results[key]?.operation && !results[key]?.stale)
    const completed = Object.values(tasks).filter(task => !['waiting', 'loading'].includes(task.state)).length
    const connection = status?.blogs[destination]

    return <section className="health-publisher" aria-labelledby="health-publisher-title">
        <header className="hp-heading"><div><p className="hp-eyebrow">YOUR OPEN NOTEBOOK</p><h2 id="health-publisher-title">Share a day of health</h2><p className="hp-muted">Fetch today and yesterday together. Create missing entries and update existing ones. Review every entry before it goes live.</p></div><span className={`hp-destination ${destination === 'production' ? 'hp-production' : ''}`}>{destination === 'production' ? 'PRODUCTION' : 'LOCAL'}</span></header>
        <div className="hp-connections">{status && Object.entries(status.providers).map(([source, provider]) => <div key={source}><strong>{sources[source]}</strong><span>{source === 'apple_health' ? provider.message : provider.connected ? 'Connected' : 'Not connected'}</span>{provider.folder && <span>{provider.folder}</span>}{provider.connected && !!provider.missing_scopes?.length && <span>Reconnect for {provider.missing_scopes.join(', ')} access</span>}{provider.needs_activity && provider.connected && <span>Activity access needs reconnection</span>}{provider.login && <a href={provider.login}>{provider.connected ? 'Reconnect' : 'Connect'}</a>}</div>)}</div>
        <div className="hp-controls"><label htmlFor="health-destination">Publish destination<select id="health-destination" value={destination} disabled={!status || busy === 'publishing' || busy === 'testing' || busy === 'saving'} onChange={e => changeDestination(e.target.value)}><option value="local">Local{status && ` · ${new URL(status.blogs.local.url).host}`}</option><option value="production">Production{status && ` · ${new URL(status.blogs.production.url).host}`}</option></select></label><Button type="button" variant="outline" disabled={!status || !!busy || !connection?.configured} onClick={testConnection}>{busy === 'testing' ? 'Testing connection…' : 'Test blog connection'}</Button><Button type="button" disabled={!status || !!busy || !connection?.configured} onClick={fetchData}>{busy === 'fetching' ? `Fetching ${completed}/${Object.keys(tasks).length}…` : busy === 'preparing' ? 'Preparing preview…' : 'Fetch health data'}</Button>{snapshot && busy !== 'publishing' && <button className="hp-text-button" type="button" onClick={() => reset()}>Cancel preview</button>}</div>
        {connection && !connection.configured && <p className="hp-notice">This destination needs its WordPress username and Application Password configured in the weight app.</p>}
        {connectionResult && <p className={connectionResult.connected ? 'hp-notice' : 'hp-error'} role="status">{connectionResult.message}{connectionResult.username && ` Signed in as ${connectionResult.username}.`}</p>}
        {error && <p className="hp-error" role="alert">{error}</p>}
        {snapshot && <p className="hp-dates">Today: <strong>{snapshot.today}</strong><span>Yesterday: <strong>{snapshot.yesterday}</strong></span><span>Europe/Bucharest</span></p>}
        {snapshot?.apple_health && <div className="hp-notice"><strong>Apple Health export: {snapshot.apple_health.folder || 'No folder'}</strong><p>{snapshot.apple_health.state === 'ok' ? `${snapshot.apple_health.from || 'No dates'} → ${snapshot.apple_health.to || 'No dates'} · ${snapshot.apple_health.metric_types} metric types · ${snapshot.apple_health.workouts} workouts · ${snapshot.apple_health.ecgs} ECG records` : snapshot.apple_health.message}</p><p>This export is frozen for this preview. New and changed entries for today and yesterday are selected by default. Unchanged entries are unchecked.</p>{snapshot.apple_health.warnings?.map(warning => <p key={warning}>{warning}</p>)}</div>}
        {!!Object.keys(tasks).length && <details className="hp-fetch-status" open={!preview}><summary>Data sources · {completed}/{Object.keys(tasks).length} complete</summary><ul>{Object.entries(tasks).map(([task, result]) => <li key={task}>
            <span>{task.replaceAll('_', ' ').replace('.', ' / ')}<span className="hp-source-date">Requested: {(result.checked_dates || snapshot.task_dates[task]).join(' & ')}</span></span>
            <span>{result.days?.length ? result.days.map(day => <span className={`hp-day-result hp-state-${day.state}`} key={day.date}><time dateTime={day.date}>{day.date}</time> · {day.message}</span>) : <span className={`hp-state-${result.state}`}>{result.message}</span>}</span>
        </li>)}</ul></details>}
        {preview && <div className="hp-preview"><div className="hp-preview-heading"><h3>Review for <span>{preview.url}</span></h3><p className="hp-muted">{Object.keys(preview.entries).length} entries · preview expires 30 minutes after fetching began</p></div>
            {!Object.keys(preview.entries).length && <p>No publishable measurements were found. Review the data-source results above.</p>}
            {Object.entries(preview.entries).map(([key, item]) => <article className={`hp-entry hp-topic-${item.entry.topic}`} key={key}><div className="hp-entry-heading"><label><input type="checkbox" checked={!!selected[key]} disabled={!!busy || !!results[key]?.operation} onChange={e => setSelected(previous => ({ ...previous, [key]: e.target.checked }))}/><strong>{status.topics[item.entry.topic]}</strong><time>{item.entry.date}</time></label><span className="hp-operation">{item.operation}</span></div>
                {!!item.retained.length && <p className="hp-notice">Retained from the existing entry: {item.retained.join(', ')}.</p>}
                <details><summary>Review all measurements and charts</summary><EntryDetails entry={item.entry} workoutTypes={status.workout_types} /></details>
                {results[key] && <p className={results[key].error ? 'hp-error' : 'hp-result'} role="status">{results[key].operation ? <>{results[key].operation} · <a href={results[key].url} target="_blank" rel="noreferrer">View entry ↗</a></> : results[key].message}</p>}
            </article>)}
            {!!Object.keys(preview.entries).length && <div className="hp-publish-bar"><p>Publish {pending.length} selected {pending.length === 1 ? 'entry' : 'entries'} to <strong>{preview.url}</strong></p><Button type="button" disabled={!!busy || !pending.length} onClick={publish}>{busy === 'publishing' ? 'Publishing…' : 'Publish selected entries'}</Button></div>}
        </div>}
        <div className="hp-live" role="status" aria-live="polite">{busy === 'fetching' ? `${completed} data requests completed` : busy === 'publishing' ? 'Publishing selected entries' : ''}</div>
    </section>
}
