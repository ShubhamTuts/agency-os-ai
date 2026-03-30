import React, { useState, useEffect } from 'react';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet } from '../services/api';
import { BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell } from 'recharts';
import { FolderKanban, CheckSquare, TrendingUp, Clock, Users } from 'lucide-react';

const COLORS = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899'];

interface ReportData {
    overview: {
        total_projects: number;
        active_projects: number;
        total_tasks: number;
        completed_tasks: number;
        overdue_tasks: number;
        total_members: number;
        hours_logged: number;
        completion_rate: number;
    };
    project_stats: Array<{ name: string; tasks: number; completed: number; progress: number }>;
    task_by_status: Array<{ status: string; count: number }>;
    task_by_priority: Array<{ priority: string; count: number }>;
    member_performance: Array<{ name: string; tasks: number; completed: number }>;
}

export default function Reports() {
    const [data, setData] = useState<ReportData | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        apiGet<any>('/aosai/v1/reports/overview').then(r => {
            if (!r) { setLoading(false); return; }
            // PHP returns the overview fields directly; adapt to the nested structure the component expects
            const normalized: ReportData = {
                overview: {
                    total_projects:  r.total_projects  ?? 0,
                    active_projects: r.active_projects ?? 0,
                    total_tasks:     r.total_tasks     ?? 0,
                    completed_tasks: r.completed_tasks ?? 0,
                    overdue_tasks:   r.overdue_tasks   ?? 0,
                    total_members:   r.total_members   ?? 0,
                    hours_logged:    r.hours_logged    ?? 0,
                    completion_rate: r.completion_rate ?? 0,
                },
                project_stats:     Array.isArray(r.project_stats)     ? r.project_stats     : [],
                task_by_status:    Array.isArray(r.task_by_status)    ? r.task_by_status    : [],
                task_by_priority:  Array.isArray(r.task_by_priority)  ? r.task_by_priority  : [],
                member_performance:Array.isArray(r.member_performance) ? r.member_performance: [],
            };
            setData(normalized);
        }).catch(() => {}).finally(() => setLoading(false));
    }, []);

    if (loading) return <DashboardLayout><LoadingSpinner /></DashboardLayout>;
    if (!data) return <DashboardLayout><div className="text-center py-16 text-gray-500">Failed to load reports</div></DashboardLayout>;

    const o = data.overview;
    const statusMap: Record<string, string> = { backlog: 'Backlog', todo: 'To Do', in_progress: 'In Progress', in_review: 'In Review', completed: 'Completed' };
    const memberPerformance = data.member_performance.map((member) => ({
        ...member,
        completionRate: member.tasks > 0 ? Math.round((member.completed / member.tasks) * 100) : 0,
    }));
    const topPerformer = [...memberPerformance].sort((a, b) => {
        if (b.completed !== a.completed) return b.completed - a.completed;
        if (b.completionRate !== a.completionRate) return b.completionRate - a.completionRate;
        return b.tasks - a.tasks;
    })[0];
    const averageCompletion = memberPerformance.length > 0
        ? Math.round(memberPerformance.reduce((sum, member) => sum + member.completionRate, 0) / memberPerformance.length)
        : 0;

    return (
        <DashboardLayout>
            <div className="max-w-7xl mx-auto space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Reports</h1>
                    <p className="text-sm text-gray-500 mt-1">Project analytics and team performance</p>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    {[
                        { label: 'Total Projects', value: o.total_projects, icon: FolderKanban },
                        { label: 'Active Projects', value: o.active_projects, icon: TrendingUp },
                        { label: 'Total Tasks', value: o.total_tasks, icon: CheckSquare },
                        { label: 'Completed', value: o.completed_tasks, icon: CheckSquare },
                        { label: 'Overdue', value: o.overdue_tasks, icon: Clock },
                        { label: 'Team Members', value: o.total_members, icon: Users },
                    ].map((s) => (
                        <div key={s.label} className="bg-white dark:bg-gray-800 rounded-xl p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                            <s.icon className="w-5 h-5 text-primary-500 mb-2" />
                            <div className="text-2xl font-bold text-gray-900 dark:text-white">{s.value}</div>
                            <div className="text-xs text-gray-500 mt-1">{s.label}</div>
                        </div>
                    ))}
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h2 className="font-semibold text-gray-900 dark:text-white mb-4">Tasks by Status</h2>
                        {data.task_by_status.length > 0 ? (
                            <ResponsiveContainer width="100%" height={250}>
                                <PieChart>
                                    <Pie data={data.task_by_status} dataKey="count" nameKey="status" cx="50%" cy="50%" outerRadius={80} label={({ status, count }) => `${statusMap[status] || status}: ${count}`}>
                                        {data.task_by_status.map((_, i) => <Cell key={i} fill={COLORS[i % COLORS.length]} />)}
                                    </Pie>
                                    <Tooltip />
                                </PieChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="h-[250px] flex items-center justify-center text-sm text-gray-500">No data</div>
                        )}
                    </div>

                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h2 className="font-semibold text-gray-900 dark:text-white mb-4">Tasks by Priority</h2>
                        {data.task_by_priority.length > 0 ? (
                            <ResponsiveContainer width="100%" height={250}>
                                <BarChart data={data.task_by_priority}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                                    <XAxis dataKey="priority" tick={{ fill: '#9ca3af', fontSize: 12 }} />
                                    <YAxis tick={{ fill: '#9ca3af', fontSize: 12 }} />
                                    <Tooltip />
                                    <Bar dataKey="count" fill="#6366f1" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="h-[250px] flex items-center justify-center text-sm text-gray-500">No data</div>
                        )}
                    </div>

                    <div className="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <h2 className="font-semibold text-gray-900 dark:text-white mb-4">Project Progress</h2>
                        {data.project_stats.length > 0 ? (
                            <ResponsiveContainer width="100%" height={300}>
                                <BarChart data={data.project_stats}>
                                    <CartesianGrid strokeDasharray="3 3" stroke="#374151" />
                                    <XAxis dataKey="name" tick={{ fill: '#9ca3af', fontSize: 12 }} />
                                    <YAxis tick={{ fill: '#9ca3af', fontSize: 12 }} />
                                    <Tooltip />
                                    <Bar dataKey="tasks" fill="#6366f1" name="Total Tasks" radius={[4, 4, 0, 0]} />
                                    <Bar dataKey="completed" fill="#10b981" name="Completed" radius={[4, 4, 0, 0]} />
                                </BarChart>
                            </ResponsiveContainer>
                        ) : (
                            <div className="h-[300px] flex items-center justify-center text-sm text-gray-500">No data</div>
                        )}
                    </div>

                    <div className="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div className="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                            <div className="lg:max-w-sm">
                                <h2 className="font-semibold text-gray-900 dark:text-white">Team Performance</h2>
                                <p className="mt-2 text-sm text-gray-500">
                                    A quick look at delivery velocity and completion quality across your active team members.
                                </p>
                                <div className="mt-5 grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                                    <div className="rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 p-4">
                                        <p className="text-xs uppercase tracking-[0.18em] text-gray-400">Top Performer</p>
                                        <p className="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{topPerformer?.name || 'No activity yet'}</p>
                                        <p className="mt-1 text-sm text-gray-500">{topPerformer ? `${topPerformer.completed} completed tasks` : 'Assignments will appear here once work starts.'}</p>
                                    </div>
                                    <div className="rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 p-4">
                                        <p className="text-xs uppercase tracking-[0.18em] text-gray-400">Team Completion</p>
                                        <p className="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{o.completion_rate}%</p>
                                        <p className="mt-1 text-sm text-gray-500">Project-wide task completion across the current workspace.</p>
                                    </div>
                                    <div className="rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 p-4">
                                        <p className="text-xs uppercase tracking-[0.18em] text-gray-400">Avg. Member Rate</p>
                                        <p className="mt-2 text-lg font-semibold text-gray-900 dark:text-white">{averageCompletion}%</p>
                                        <p className="mt-1 text-sm text-gray-500">Average personal completion rate among visible contributors.</p>
                                    </div>
                                </div>
                            </div>

                            <div className="flex-1 space-y-4">
                                {memberPerformance.length > 0 ? memberPerformance.map((member, index) => (
                                    <div key={`${member.name}-${index}`} className="rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
                                        <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                            <div>
                                                <p className="font-medium text-gray-900 dark:text-white">{member.name}</p>
                                                <p className="text-sm text-gray-500">{member.completed} completed of {member.tasks} assigned tasks</p>
                                            </div>
                                            <div className="text-sm font-semibold text-primary-600">
                                                {member.completionRate}% completion
                                            </div>
                                        </div>
                                        <div className="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                            <div
                                                className="h-full rounded-full bg-gradient-to-r from-primary-500 via-primary-600 to-emerald-500"
                                                style={{ width: `${Math.max(member.completionRate, member.tasks > 0 ? 8 : 0)}%` }}
                                            />
                                        </div>
                                    </div>
                                )) : (
                                    <div className="h-[180px] flex items-center justify-center text-sm text-gray-500">No member performance data yet</div>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
