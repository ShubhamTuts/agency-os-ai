import React, { useState } from 'react';
import { useAppContext } from '../../context/AppContext';
import { Sidebar } from '../ui/Sidebar';
import { TopBar } from '../ui/TopBar';
import { AiAssistantDrawer } from '../ui/AiAssistantDrawer';

interface DashboardLayoutProps {
    children: React.ReactNode;
}

export function DashboardLayout({ children }: DashboardLayoutProps) {
    const { state, dispatch } = useAppContext();
    const [aiAssistantOpen, setAiAssistantOpen] = useState(false);
    
    return (
        <div className="aosai-app-container flex h-screen bg-gray-50 dark:bg-gray-900 overflow-hidden">
            <Sidebar
                collapsed={state.sidebarCollapsed}
                onToggle={() => dispatch({ type: 'TOGGLE_SIDEBAR' })}
            />
            
            <div className="flex-1 flex flex-col overflow-hidden">
                <TopBar onOpenAiAssistant={() => setAiAssistantOpen(true)} />
                
                <main className="flex-1 overflow-y-auto p-6">
                    {children}
                </main>
            </div>

            <AiAssistantDrawer open={aiAssistantOpen} onClose={() => setAiAssistantOpen(false)} />
        </div>
    );
}
