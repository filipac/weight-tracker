import test from 'node:test'
import assert from 'node:assert/strict'
import { runConcurrent } from '../../resources/js/lib/runConcurrent.js'

const tick = () => new Promise(resolve => setImmediate(resolve))

test('four tasks overlap, completed slots refill, and every task runs once', async () => {
    const started = [], pending = new Map()
    let active = 0, peak = 0
    const work = runConcurrent([0, 1, 2, 3, 4, 5], 4, item => {
        started.push(item)
        peak = Math.max(peak, ++active)
        return new Promise(resolve => pending.set(item, () => { active--; resolve() }))
    })
    assert.deepEqual(started, [0, 1, 2, 3])
    pending.get(2)()
    await tick()
    assert.deepEqual(started, [0, 1, 2, 3, 4])
    pending.get(0)()
    await tick()
    assert.deepEqual(started, [0, 1, 2, 3, 4, 5])
    for (const i of [1, 3, 4, 5]) pending.get(i)()
    await work
    assert.equal(peak, 4)
    assert.equal(active, 0)
})

test('cancellation or destination switching stops queued tasks', async () => {
    let current = true
    const started = [], finish = []
    const work = runConcurrent([0, 1, 2, 3], 2, item => {
        started.push(item)
        return new Promise(resolve => finish.push(resolve))
    }, () => current)
    current = false
    finish.forEach(resolve => resolve())
    await work
    assert.deepEqual(started, [0, 1])
})

test('HTTP failure waits for in-flight tasks and prevents further dispatch', async () => {
    const error = new Error('Request failed')
    let rejectFirst, finishSecond, settled = false
    const started = []
    const work = runConcurrent([0, 1, 2], 2, item => {
        started.push(item)
        return new Promise((resolve, reject) => {
            if (item === 0) rejectFirst = reject
            else finishSecond = resolve
        })
    }).catch(e => { settled = true; assert.equal(e, error) })
    rejectFirst(error)
    await tick()
    assert.equal(settled, false)
    finishSecond()
    await work
    assert.deepEqual(started, [0, 1])
    assert.equal(settled, true)
})

test('resolved provider errors still allow other categories to complete', async () => {
    const completed = []
    await runConcurrent([0, 1, 2], 4, async item => {
        completed.push(item)
        return { state: item === 0 ? 'error' : 'ok' }
    })
    assert.deepEqual(completed, [0, 1, 2])
})
