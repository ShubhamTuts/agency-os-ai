import React, { useEffect, useState } from 'react';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPost } from '../services/api';
import { Search, UserPlus, Shield } from 'lucide-react';

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

export default function Team() {
    const [users, setUsers] = useState<User[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [inviting, setInviting] = useState(false);
    const [inviteEmail, setInviteEmail] = useState('');
    const [inviteRole, setInviteRole] = useState('aosai_employee');

    useEffect(() => { loadUsers(); }, [search]);

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

    async function handleInvite(e: React.FormEvent) {
        e.preventDefault();
        if (!inviteEmail.trim()) return;
        setInviting(true);
        try {
            await apiPost('/aosai/v1/users/invite', { email: inviteEmail, role: inviteRole });
            setInviteEmail('');
            await loadUsers();
            alert('Invitation sent successfully.');
        } catch (err: any) {
            alert(err.message || 'Unable to send invitation.');
        } finally {
            setInviting(false);
        }
    }

    return (
        <DashboardLayout>
            <div className="max-w-6xl mx-auto space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Access Management</h1>
                        <p className="text-sm text-gray-500 mt-1">Invite employees, onboard clients, and review workspace access at a glance.</p>
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

                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <input type="text" placeholder="Search people..." value={search} onChange={(e) => setSearch(e.target.value)} className="w-full pl-9 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm" />
                </div>

                {loading ? <LoadingSpinner /> : users.length === 0 ? (
                    <div className="text-center py-16 text-sm text-gray-500">No users found</div>
                ) : (
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
                                            <span className="inline-flex items-center gap-1 text-xs px-2.5 py-1.5 bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 rounded-full">
                                                <Shield className="w-3 h-3" /> {ROLE_LABELS[user.roles?.[0]] || user.roles?.[0] || 'Member'}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-gray-500">{user.project_count ?? 0}</td>
                                        <td className="px-4 py-3 text-gray-500">{user.task_count ?? 0}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}

