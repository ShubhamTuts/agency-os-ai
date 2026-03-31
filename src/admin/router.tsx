import { Routes, Route, Navigate } from 'react-router-dom';
import Dashboard from './pages/Dashboard';
import Projects from './pages/Projects';
import ProjectDetail from './pages/ProjectDetail';
import TaskDetail from './pages/TaskDetail';
import Milestones from './pages/Milestones';
import Messages from './pages/Messages';
import Files from './pages/Files';
import Reports from './pages/Reports';
import Team from './pages/Team';
import Settings from './pages/Settings';
import AiPlayground from './pages/AiPlayground';
import Profile from './pages/Profile';
import Clients from './pages/Clients';
import Invoices from './pages/Invoices';
import TimeTracking from './pages/TimeTracking';

function ProRoute({ children }: { children: React.ReactNode }) {
    const isPro = (window as any).aosaiData?.isPro;
    if (!isPro) {
        return (
            <div className="flex items-center justify-center h-64">
                <div className="text-center">
                    <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                        Pro Feature
                    </h2>
                    <p className="text-gray-500 mb-4">
                        Upgrade to Agency OS AI Pro to access this feature.
                    </p>
                    <a
                        href="https://codefreex.com/agency-os-ai-pro"
                        target="_blank"
                        rel="noopener noreferrer"
                        className="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700"
                    >
                        Upgrade to Pro
                    </a>
                </div>
            </div>
        );
    }
    return <>{children}</>;
}

export function AppRouter() {
    return (
        <Routes>
            <Route path="/" element={<Dashboard />} />
            <Route path="/projects" element={<Projects />} />
            <Route path="/projects/:id/*" element={<ProjectDetail />} />
            <Route path="/tasks/:id" element={<TaskDetail />} />
            <Route path="/milestones" element={<Milestones />} />
            <Route path="/messages" element={<Messages />} />
            <Route path="/files" element={<Files />} />
            <Route path="/reports" element={<Reports />} />
            <Route path="/team" element={<Team />} />
            <Route path="/settings" element={<Settings />} />
            <Route path="/ai" element={<AiPlayground />} />
            <Route path="/profile" element={<Profile />} />
            <Route path="/clients" element={<ProRoute><Clients /></ProRoute>} />
            <Route path="/invoices" element={<ProRoute><Invoices /></ProRoute>} />
            <Route path="/time-tracking" element={<ProRoute><TimeTracking /></ProRoute>} />
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}
