import React, { createContext, useContext, useReducer, ReactNode } from 'react';

interface AppState {
    sidebarCollapsed: boolean;
    aiPanelOpen: boolean;
    currentProject: number | null;
}

type AppAction =
    | { type: 'TOGGLE_SIDEBAR' }
    | { type: 'SET_SIDEBAR'; payload: boolean }
    | { type: 'TOGGLE_AI_PANEL' }
    | { type: 'SET_AI_PANEL'; payload: boolean }
    | { type: 'SET_CURRENT_PROJECT'; payload: number | null };

const initialState: AppState = {
    sidebarCollapsed: false,
    aiPanelOpen: false,
    currentProject: null,
};

function appReducer(state: AppState, action: AppAction): AppState {
    switch (action.type) {
        case 'TOGGLE_SIDEBAR':
            return { ...state, sidebarCollapsed: !state.sidebarCollapsed };
        case 'SET_SIDEBAR':
            return { ...state, sidebarCollapsed: action.payload };
        case 'TOGGLE_AI_PANEL':
            return { ...state, aiPanelOpen: !state.aiPanelOpen };
        case 'SET_AI_PANEL':
            return { ...state, aiPanelOpen: action.payload };
        case 'SET_CURRENT_PROJECT':
            return { ...state, currentProject: action.payload };
        default:
            return state;
    }
}

interface AppContextType {
    state: AppState;
    dispatch: React.Dispatch<AppAction>;
}

const AppContext = createContext<AppContextType | undefined>(undefined);

export function AppProvider({ children }: { children: ReactNode }) {
    const [state, dispatch] = useReducer(appReducer, initialState);

    return (
        <AppContext.Provider value={{ state, dispatch }}>
            {children}
        </AppContext.Provider>
    );
}

export function useAppContext() {
    const context = useContext(AppContext);
    if (context === undefined) {
        throw new Error('useAppContext must be used within an AppProvider');
    }
    return context;
}
