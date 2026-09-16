// Drain in-flight requests before reporting a failure; never start more after
// cancellation or an HTTP failure. Provider errors are normal task results.
export async function runConcurrent(items, limit, run, shouldContinue = () => true) {
    const queue = [...items]
    let failure
    let failed = false
    const worker = async () => {
        while (queue.length && !failed && shouldContinue()) {
            const item = queue.shift()
            try {
                await run(item)
            } catch (error) {
                failed = true
                failure ??= error
            }
        }
    }
    const workers = Math.max(1, Math.min(6, Number(limit) || 4))
    await Promise.all(Array.from({ length: Math.min(workers, queue.length) }, worker))
    if (failed) throw failure
}
