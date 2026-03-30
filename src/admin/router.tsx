import { Routes, Route, Navigate } from 'react-router-dom';
import { lazy, Suspense } from 'react';
import { LoadingSpinner } from './components/ui/LoadingSpinner';

const Dashboard = lazy(() => import('./pages/Dashboard'));
const Projects = lazy(() => import('./pages/Projects'));
const ProjectDetail = lazy(() => import('./pages/ProjectDetail'));
const TaskDetail = lazy(() => import('./pages/TaskDetail'));
const Milestones = lazy(() => import('./pages/Milestones'));
const Messages = lazy(() => import('./pages/Messages'));
const Files = lazy(() => import('./pages/Files'));
const Reports = lazy(() => import('./pages/Reports'));
const Team = lazy(() => import('./pages/Team'));
const Settings = lazy(() => import('./pages/Settings'));
const AiPlayground = lazy(() => import('./pages/AiPlayground'));
const Profile = lazy(() => import('./pages/Profile'));

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
        <Suspense fallback={<LoadingSpinner />}>
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
                <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
        </Suspense>
    );
}
