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
            <Route path="/clients" element={<Clients />} />
            <Route path="/invoices" element={<Invoices />} />
            <Route path="/time-tracking" element={<TimeTracking />} />
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}
