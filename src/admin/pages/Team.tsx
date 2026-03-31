import React, { useEffect, useMemo, useState } from 'react';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPost } from '../services/api';
import { BriefcaseBusiness, ClipboardList, Search, Shield, Sparkles, UserPlus, Users } from 'lucide-react';

interface User {
    id: number;
    name: string;
    email: string;
    display_name: string;
    roles: string[];
    portal_type?: string;
    project_count: number;
    task_count: number;
}

interface MemberPerformance {
    name: string;
    tasks: number;
    completed: number;
}

interface ReportsOverview {
    total_projects: number;
    active_projects: number;
    total_tasks: number;
    completed_tasks: number;
    overdue_tasks: number;
    total_members: number;
    completion_rate: number;
    member_performance: MemberPerformance[];
}

interface TeamBrief {
    source: 'ai' | 'fallback';
    summary: string;
    coaching_points: string[];
    spotlight: string;
    risks: string[];
    message?: string;
}

const ROLE_OPTIONS = [
    { value: 'aosai_employee', label: 'Employee' },
    { value: 'aosai_client', label: 'Client' },
    { value: 'editor', label: 'Manager' },
    { value: 'administrator', label: 'Administrator' },
];

const ROLE_LABELS: Record<string, string> = {
    aosai_employee: 'Employee',
    aosai_client: 'Client',
    editor: 'Manager',
    administrator: 'Administrator',
    author: 'Contributor',
    subscriber: 'Subscriber',
};

function StatTile({
    label,
    value,
    hint,
    icon: Icon,
}: {
    label: string;
    value: number | string;
    hint: string;
    icon: React.ComponentType<{ className?: string }>;
}) {
    return (
        <div className="bg-white dark:bg-gray-800 rounded-2xl p-5 shadow-sm border border-gray-200 dark:border-gray-700">
            <Icon className="w-5 h-5 text-primary-500" />
            <div className="mt-3 text-2xl font-bold text-gray-900 dark:text-white">{value}</div>
            <div className="mt-1 text-sm font-medium text-gray-700 dark:text-gray-200">{label}</div>
            <div className="mt-1 text-xs text-gray-500">{hint}</div>
        </div>
    );
}

export default function Team() {
    const [users, setUsers] = useState<User[]>([]);
    const [reports, setReports] = useState<ReportsOverview | null>(null);
    const [loading, setLoading] = useState(true);
    const [reportsLoading, setReportsLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [inviting, setInviting] = useState(false);
    const [inviteEmail, setInviteEmail] = useState('');
    const [inviteRole, setInviteRole] = useState('aosai_employee');
    const [briefLoading, setBriefLoading] = useState(false);
    const [teamBrief, setTeamBrief] = useState<TeamBrief | null>(null);

    useEffect(() => { loadUsers(); }, [search]);
    useEffect(() => { loadReports(); }, []);

    async function loadUsers() {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (search) params.search = search;
            const res = await apiGet<User[]>('/aosai/v1/users/list', params);
            setUsers(Array.isArray(res) ? res : []);
        } catch {
            setUsers([]);
        } finally {
            setLoading(false);
        }
    }

    async function loadReports() {
        setReportsLoading(true);
        try {
            const res = await apiGet<ReportsOverview>('/aosai/v1/reports/overview');
            setReports(res || null);
        } catch {
            setReports(null);
        } finally {
            setReportsLoading(false);
        }
    }

    async function handleInvite(e: React.FormEvent) {
        e.preventDefault();
        if (!inviteEmail.trim()) return;
        setInviting(true);
        try {
            await apiPost('/aosai/v1/users/invite', { email: inviteEmail, role: inviteRole });
            setInviteEmail('');
            await Promise.all([loadUsers(), loadReports()]);
            alert('Invitation sent successfully.');
        } catch (err: any) {
            alert(err.message || 'Unable to send invitation.');
        } finally {
            setInviting(false);
        }
    }

    async function handleGenerateBrief() {
        setBriefLoading(true);
        try {
            const result = await apiPost<TeamBrief>('/aosai/v1/ai/team-brief', {
                overview: reports || {},
                member_performance: reports?.member_performance || [],
                users,
            });
            setTeamBrief(result);
        } catch (err: any) {
            setTeamBrief({
                source: 'fallback',
                summary: err.message || 'Unable to generate the team brief right now.',
                coaching_points: [],
                spotlight: 'No coaching spotlight available yet.',
                risks: [],
            });
        } finally {
            setBriefLoading(false);
        }
    }

    const teamStats = useMemo(() => {
        const clientCount = users.filter((user) => user.portal_type === 'client').length;
        const employeeCount = users.filter((user) => user.portal_type !== 'client').length;
        const totalProjectAssignments = users.reduce((sum, user) => sum + (user.project_count || 0), 0);
        const totalTaskAssignments = users.reduce((sum, user) => sum + (user.task_count || 0), 0);
        const mostLoaded = [...users].sort((left, right) => (right.task_count || 0) - (left.task_count || 0))[0];

        return {
            clientCount,
            employeeCount,
            totalProjectAssignments,
            totalTaskAssignments,
            mostLoaded,
        };
    }, [users]);

    const memberPerformance = useMemo(() => {
        return (reports?.member_performance || []).map((member) => ({
            ...member,
            completionRate: member.tasks > 0 ? Math.round((member.completed / member.tasks) * 100) : 0,
        }));
    }, [reports]);

    const topPerformer = useMemo(() => {
        return [...memberPerformance].sort((left, right) => {
            if (right.completed !== left.completed) return right.completed - left.completed;
            return right.completionRate - left.completionRate;
        })[0];
    }, [memberPerformance]);

    const averageCompletionRate = memberPerformance.length > 0
        ? Math.round(memberPerformance.reduce((sum, member) => sum + member.completionRate, 0) / memberPerformance.length)
        : 0;

    return (
        <DashboardLayout>
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Access Management</h1>
                        <p className="text-sm text-gray-500 mt-1">Invite employees, onboard clients, and manage delivery visibility from one place.</p>
                    </div>
                    <form onSubmit={handleInvite} className="grid gap-2 md:grid-cols-[1fr_180px_auto]">
                        <input type="email" value={inviteEmail} onChange={(e) => setInviteEmail(e.target.value)} placeholder="Email address" className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm min-w-[260px]" />
                        <select value={inviteRole} onChange={(e) => setInviteRole(e.target.value)} className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm">
                            {ROLE_OPTIONS.map((role) => <option key={role.value} value={role.value}>{role.label}</option>)}
                        </select>
                        <button type="submit" disabled={inviting} className="inline-flex items-center justify-center gap-1.5 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm disabled:opacity-50">
                            <UserPlus className="w-4 h-4" /> {inviting ? 'Sending...' : 'Invite'}
                        </button>
                    </form>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <StatTile label="Clients" value={teamStats.clientCount} hint="Portal users with client visibility" icon={Users} />
                    <StatTile label="Team Members" value={teamStats.employeeCount} hint="Employees, managers, and administrators" icon={Shield} />
                    <StatTile label="Project Assignments" value={teamStats.totalProjectAssignments} hint="Total member to project relationships" icon={BriefcaseBusiness} />
                    <StatTile label="Task Assignments" value={teamStats.totalTaskAssignments} hint="Current work ownership across the workspace" icon={ClipboardList} />
                    <StatTile label="Completion Rate" value={`${reports?.completion_rate ?? 0}%`} hint="Workspace-wide task completion progress" icon={Sparkles} />
                    <StatTile label="Overdue Tasks" value={reports?.overdue_tasks ?? 0} hint="Delivery items that need recovery planning" icon={ClipboardList} />
                </div>

                <div className="grid gap-6 xl:grid-cols-[1.2fr_.8fr]">
                    <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div className="max-w-2xl">
                                <h2 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                                    <Sparkles className="w-5 h-5 text-primary-500" /> AI Team Coach
                                </h2>
                                <p className="mt-2 text-sm text-gray-500">
                                    Turn live workspace activity into coaching points, delivery risks, and the next best management actions for your team.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={handleGenerateBrief}
                                disabled={briefLoading || reportsLoading}
                                className="inline-flex items-center gap-2 rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                            >
                                <Sparkles className="w-4 h-4" /> {briefLoading ? 'Generating...' : (teamBrief ? 'Refresh Brief' : 'Generate Brief')}
                            </button>
                        </div>

                        {teamBrief ? (
                            <div className="mt-6 grid gap-4 xl:grid-cols-[1.05fr_.95fr]">
                                <div className="rounded-3xl border border-primary-100 bg-primary-50/60 p-5">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-primary-500">Team Summary</p>
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${teamBrief.source === 'ai' ? 'bg-primary-600 text-white' : 'bg-white text-primary-700 border border-primary-200'}`}>
                                            {teamBrief.source === 'ai' ? 'AI Powered' : 'Fallback Logic'}
                                        </span>
                                    </div>
                                    <p className="mt-3 text-sm leading-7 text-gray-700">{teamBrief.summary}</p>
                                    <div className="mt-4 rounded-2xl border border-white/80 bg-white px-4 py-4">
                                        <p className="text-xs uppercase tracking-[0.18em] text-gray-400">Spotlight</p>
                                        <p className="mt-2 text-sm font-medium text-gray-800">{teamBrief.spotlight}</p>
                                    </div>
                                    {teamBrief.message ? <p className="mt-3 text-xs text-gray-500">{teamBrief.message}</p> : null}
                                </div>
                                <div className="grid gap-4">
                                    <div className="rounded-3xl border border-gray-200 p-5">
                                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Coaching Points</p>
                                        <ul className="mt-3 space-y-2 text-sm text-gray-600">
                                            {teamBrief.coaching_points.length > 0 ? teamBrief.coaching_points.map((item, index) => <li key={index}>- {item}</li>) : <li>No coaching points returned.</li>}
                                        </ul>
                                    </div>
                                    <div className="rounded-3xl border border-rose-100 bg-rose-50/50 p-5">
                                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-rose-400">Risks</p>
                                        <ul className="mt-3 space-y-2 text-sm text-rose-700">
                                            {teamBrief.risks.length > 0 ? teamBrief.risks.map((item, index) => <li key={index}>- {item}</li>) : <li>No major risks detected.</li>}
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="mt-6 rounded-3xl border border-dashed border-gray-200 px-5 py-10 text-sm text-gray-500">
                                Generate a team brief to turn assignments, report data, and team activity into a practical management summary.
                            </div>
                        )}
                    </div>

                    <div className="space-y-4">
                        <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                            <p className="text-xs uppercase tracking-[0.18em] text-gray-400">Top Performer</p>
                            <p className="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{topPerformer?.name || 'No activity yet'}</p>
                            <p className="mt-2 text-sm text-gray-500">{topPerformer ? `${topPerformer.completed} completed of ${topPerformer.tasks} assigned tasks` : 'Performance data will appear here once tasks are completed.'}</p>
                        </div>
                        <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                            <p className="text-xs uppercase tracking-[0.18em] text-gray-400">Most Loaded Teammate</p>
                            <p className="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{teamStats.mostLoaded?.display_name || teamStats.mostLoaded?.name || 'No workload yet'}</p>
                            <p className="mt-2 text-sm text-gray-500">{teamStats.mostLoaded ? `${teamStats.mostLoaded.task_count || 0} assigned tasks and ${teamStats.mostLoaded.project_count || 0} project assignments` : 'Once work is assigned, workload pressure will show here.'}</p>
                        </div>
                        <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                            <p className="text-xs uppercase tracking-[0.18em] text-gray-400">Average Member Completion</p>
                            <p className="mt-2 text-xl font-semibold text-gray-900 dark:text-white">{averageCompletionRate}%</p>
                            <p className="mt-2 text-sm text-gray-500">Average task completion rate across visible team contributors.</p>
                        </div>
                    </div>
                </div>

                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <input type="text" placeholder="Search people..." value={search} onChange={(e) => setSearch(e.target.value)} className="w-full pl-9 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm" />
                </div>

                {loading ? <LoadingSpinner /> : users.length === 0 ? (
                    <div className="text-center py-16 text-sm text-gray-500">No users found</div>
                ) : (
                    <div className="grid gap-6 xl:grid-cols-[1.15fr_.85fr]">
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <table className="w-full text-sm">
                                <thead className="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th className="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Member</th>
                                        <th className="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Access</th>
                                        <th className="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Projects</th>
                                        <th className="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Tasks</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                    {users.map((user) => (
                                        <tr key={user.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-3">
                                                    <div className="w-10 h-10 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 flex items-center justify-center text-sm font-medium">
                                                        {user.display_name?.charAt(0) || user.name?.charAt(0) || '?'}
                                                    </div>
                                                    <div>
                                                        <p className="font-medium text-gray-900 dark:text-white">{user.display_name || user.name}</p>
                                                        <p className="text-xs text-gray-500">{user.email}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-2">
                                                    <span className="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full">
                                                        <Shield className="w-3 h-3" /> {ROLE_LABELS[user.roles?.[0]] || user.roles?.[0] || 'Member'}
                                                    </span>
                                                    {user.portal_type ? (
                                                        <span className="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 bg-primary-50 dark:bg-primary-900/20 text-primary-700 dark:text-primary-300 rounded-full">
                                                            {user.portal_type}
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-gray-500">{user.project_count ?? 0}</td>
                                            <td className="px-4 py-3 text-gray-500">{user.task_count ?? 0}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                            <div className="flex items-center justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold text-gray-900 dark:text-white">Delivery Performance</h2>
                                    <p className="mt-1 text-sm text-gray-500">Live completion and workload signals from the workspace report.</p>
                                </div>
                            </div>

                            {reportsLoading ? (
                                <div className="py-10"><LoadingSpinner /></div>
                            ) : memberPerformance.length > 0 ? (
                                <div className="mt-5 space-y-4">
                                    {memberPerformance.map((member, index) => (
                                        <div key={`${member.name}-${index}`} className="rounded-2xl border border-gray-200 dark:border-gray-700 p-4">
                                            <div className="flex items-center justify-between gap-3">
                                                <div>
                                                    <p className="font-medium text-gray-900 dark:text-white">{member.name}</p>
                                                    <p className="mt-1 text-sm text-gray-500">{member.completed} completed of {member.tasks} assigned tasks</p>
                                                </div>
                                                <div className="text-sm font-semibold text-primary-600">{member.completionRate}%</div>
                                            </div>
                                            <div className="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                                                <div
                                                    className="h-full rounded-full bg-gradient-to-r from-primary-500 via-primary-600 to-emerald-500"
                                                    style={{ width: `${Math.max(member.completionRate, member.tasks > 0 ? 8 : 0)}%` }}
                                                />
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="py-12 text-center text-sm text-gray-500">No member performance data yet</div>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
