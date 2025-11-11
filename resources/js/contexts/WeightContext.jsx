import { createContext, useContext } from 'react'

const WeightContext = createContext(null)

export function WeightProvider({ children, weightChanges }) {
    return (
        <WeightContext.Provider value={weightChanges}>
            {children}
        </WeightContext.Provider>
    )
}

export function useWeightChanges() {
    const context = useContext(WeightContext)
    return context
}
