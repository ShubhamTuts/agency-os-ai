import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet } from '../services/api';
import {
    FolderKanban,
    CheckSquare,
    Users,
    TrendingUp,
    Clock,
    AlertCircle,
    Calendar,
    ArrowRight,
    Activity,
} from 'lucide-react';

interface Stats {
    total_projects: number;
    active_projects: number;
    total_tasks: number;
    completed_tasks: number;
    overdue_tasks: number;
    total_members: number;
    hours_logged: number;
}

interface Activity {
    id: number;
    activity_type: string;
    description: string;
    user_name: string;
    created_at: string;
}

interface Task {
    id: number;
    title: string;
    status: string;
    priority: string;
    due_date: string;
    project_name: string;
    assignee_name: string;
}

interface Project {
    id: number;
    name: string;
    status: string;
    progress: number;
    task_count: number;
}

export default function Dashboard() {
    const navigate = useNavigate();
    const [stats, setStats] = useState<Stats | null>(null);
    const [recentActivity, setRecentActivity] = useState<Activity[]>([]);
    const [myTasks, setMyTasks] = useState<Task[]>([]);
    const [projects, setProjects] = useState<Project[]>([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        Promise.all([
            apiGet<Stats>('/aosai/v1/reports/overview').catch(() => null),
            apiGet<Activity[]>('/aosai/v1/activities/recent', { per_page: 10 }).catch(() => []),
            apiGet<Task[]>('/aosai/v1/tasks/my-tasks').catch(() => []),
            apiGet<Project[]>('/aosai/v1/projects', { per_page: 5 }).catch(() => []),
        ]).then(([s, a, t, p]) => {
            setStats(s);
            setRecentActivity(a as Activity[]);
            setMyTasks(t as Task[]);
            setProjects(p as Project[]);
        }).catch(() => {
            setStats({ total_projects: 0, active_projects: 0, total_tasks: 0, completed_tasks: 0, overdue_tasks: 0, total_members: 0, hours_logged: 0 });
        }).finally(() => setLoading(false));
    }, []);

    if (loading) return <DashboardLayout><div className="flex items-center justify-center h-64"><LoadingSpinner /></div></DashboardLayout>;

    const statCards = [
        { label: 'Total Projects', value: stats?.total_projects ?? 0, icon: FolderKanban, color: 'bg-blue-500' },
        { label: 'Active Projects', value: stats?.active_projects ?? 0, icon: TrendingUp, color: 'bg-green-500' },
        { label: 'Total Tasks', value: stats?.total_tasks ?? 0, icon: CheckSquare, color: 'bg-purple-500' },
        { label: 'Completed', value: stats?.completed_tasks ?? 0, icon: CheckSquare, color: 'bg-emerald-500' },
        { label: 'Overdue', value: stats?.overdue_tasks ?? 0, icon: AlertCircle, color: 'bg-red-500' },
        { label: 'Team Members', value: stats?.total_members ?? 0, icon: Users, color: 'bg-orange-500' },
    ];

    return (
        <DashboardLayout>
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                        <p className="text-sm text-gray-500 mt-1">Welcome back! Here's what's happening.</p>
                    </div>
                    <button
                        onClick={() => navigate('/projects')}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium"
                    >
                        New Project <ArrowRight className="w-4 h-4" />
                    </button>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    {statCards.map((card) => (
                        <div key={card.label} className="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                            <div className="flex items-center justify-between mb-3">
                                <div className={`${card.color} p-2 rounded-lg`}>
                                    <card.icon className="w-4 h-4 text-white" />
                                </div>
                            </div>
                            <div className="text-2xl font-bold text-gray-900 dark:text-white">{card.value}</div>
                            <div className="text-xs text-gray-500 mt-1">{card.label}</div>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div className="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <h2 className="font-semibold text-gray-900 dark:text-white">My Tasks</h2>
                            <button onClick={() => navigate('/projects')} className="text-xs text-primary-600 hover:text-primary-700">View all</button>
                        </div>
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {myTasks.length === 0 ? (
                                <div className="px-5 py-8 text-center text-sm text-gray-500">No tasks assigned to you</div>
                            ) : (
                                myTasks.map((task) => (
                                    <div
                                        key={task.id}
                                        onClick={() => navigate(`/tasks/${task.id}`)}
                                        className="px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer flex items-center gap-3"
                                    >
                                        <div className={`w-2 h-2 rounded-full flex-shrink-0 ${task.status === 'completed' ? 'bg-green-500' : task.status === 'in_progress' ? 'bg-blue-500' : 'bg-gray-400'}`} />
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-medium text-gray-900 dark:text-white truncate">{task.title}</p>
                                            <p className="text-xs text-gray-500">{task.project_name} &middot; {task.assignee_name}</p>
                                        </div>
                                        <span className={`text-xs px-2 py-1 rounded-full font-medium ${task.priority === 'high' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : task.priority === 'medium' ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'}`}>
                                            {task.priority}
                                        </span>
                                        {task.due_date && (
                                            <div className="flex items-center gap-1 text-xs text-gray-500">
                                                <Calendar className="w-3 h-3" />
                                                {new Date(task.due_date).toLocaleDateString()}
                                            </div>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    </div>

                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div className="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <h2 className="font-semibold text-gray-900 dark:text-white">Recent Activity</h2>
                        </div>
                        <div className="divide-y divide-gray-100 dark:divide-gray-700">
                            {recentActivity.length === 0 ? (
                                <div className="px-5 py-8 text-center text-sm text-gray-500">No recent activity</div>
                            ) : (
                                recentActivity.map((activity) => (
                                    <div key={activity.id} className="px-5 py-3">
                                        <p className="text-sm text-gray-700 dark:text-gray-300">
                                            <span className="font-medium text-gray-900 dark:text-white">{activity.user_name}</span>{' '}
                                            {activity.description}
                                        </p>
                                        <p className="text-xs text-gray-400 mt-1 flex items-center gap-1">
                                            <Clock className="w-3 h-3" />
                                            {new Date(activity.created_at).toLocaleString()}
                                        </p>
                                    </div>
                                ))
                            )}
                        </div>
                    </div>
                </div>

                <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                    <div className="px-5 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h2 className="font-semibold text-gray-900 dark:text-white">Projects</h2>
                        <button onClick={() => navigate('/projects')} className="text-xs text-primary-600 hover:text-primary-700">View all</button>
                    </div>
                    <div className="divide-y divide-gray-100 dark:divide-gray-700">
                        {projects.length === 0 ? (
                            <div className="px-5 py-8 text-center text-sm text-gray-500">No projects yet</div>
                        ) : (
                            projects.map((project) => (
                                <div
                                    key={project.id}
                                    onClick={() => navigate(`/projects/${project.id}`)}
                                    className="px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer flex items-center gap-4"
                                >
                                    <div className="flex-1 min-w-0">
                                        <p className="text-sm font-medium text-gray-900 dark:text-white truncate">{project.name}</p>
                                        <div className="flex items-center gap-2 mt-1">
                                            <div className="flex-1 h-1.5 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                                <div
                                                    className="h-full bg-primary-500 rounded-full transition-all"
                                                    style={{ width: `${project.progress}%` }}
                                                />
                                            </div>
                                            <span className="text-xs text-gray-500">{project.progress}%</span>
                                        </div>
                                    </div>
                                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${project.status === 'active' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : project.status === 'completed' ? 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400' : 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'}`}>
                                        {project.status}
                                    </span>
                                </div>
                            ))
                        )}
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
