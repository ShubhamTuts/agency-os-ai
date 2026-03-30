import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPost } from '../services/api';
import { Plus, Calendar, Flag, CheckSquare, X, Edit2 } from 'lucide-react';

interface Milestone {
    id: number;
    name: string;
    description: string;
    due_date: string;
    status: string;
    project_id: number;
    project_name: string;
    task_count: number;
    completed_task_count: number;
    progress: number;
}

export default function Milestones() {
    const navigate = useNavigate();
    const [milestones, setMilestones] = useState<Milestone[]>([]);
    const [loading, setLoading] = useState(true);
    const [showModal, setShowModal] = useState(false);
    const [newMile, setNewMile] = useState({ name: '', description: '', due_date: '', project_id: 0 });
    const [projects, setProjects] = useState<any[]>([]);

    useEffect(() => {
        Promise.all([
            apiGet<Milestone[]>('/aosai/v1/milestones').catch(() => []),
            apiGet<any[]>('/aosai/v1/projects').catch(() => []),
        ]).then(([m, p]) => {
            setMilestones(Array.isArray(m) ? m : []);
            setProjects(Array.isArray(p) ? p : []);
        }).finally(() => setLoading(false));
    }, []);

    async function handleCreate(e: React.FormEvent) {
        e.preventDefault();
        if (!newMile.name.trim()) return;
        try {
            const res = await apiPost<Milestone>('/aosai/v1/milestones', newMile);
            setMilestones(prev => [...prev, res]);
            setShowModal(false);
            setNewMile({ name: '', description: '', due_date: '', project_id: 0 });
        } catch (err: any) { alert(err.message); }
    }

    const statusColors: Record<string, string> = {
        upcoming: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        completed: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
        overdue: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    };

    return (
        <DashboardLayout>
            <div className="max-w-5xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Milestones</h1>
                        <p className="text-sm text-gray-500 mt-1">Track major project goals and deadlines</p>
                    </div>
                    <button onClick={() => setShowModal(true)} className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium">
                        <Plus className="w-4 h-4" /> New Milestone
                    </button>
                </div>

                {loading ? <LoadingSpinner /> : milestones.length === 0 ? (
                    <div className="text-center py-16">
                        <Flag className="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-3" />
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white">No milestones yet</h3>
                        <p className="text-sm text-gray-500 mt-1">Create your first milestone to track project goals</p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {milestones.map((m) => (
                            <div key={m.id} className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
                                <div className="flex items-start justify-between mb-3">
                                    <div>
                                        <h3 className="font-semibold text-gray-900 dark:text-white">{m.name}</h3>
                                        <p className="text-sm text-gray-500 mt-1">{m.project_name}</p>
                                    </div>
                                    <span className={`text-xs px-2 py-1 rounded-full font-medium ${statusColors[m.status] || statusColors.upcoming}`}>
                                        {m.status?.replace('_', ' ')}
                                    </span>
                                </div>
                                {m.description && <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">{m.description}</p>}
                                <div className="flex items-center gap-4 mb-3">
                                    <div className="flex items-center gap-1 text-sm text-gray-500">
                                        <Calendar className="w-4 h-4" /> {m.due_date ? new Date(m.due_date).toLocaleDateString() : 'No due date'}
                                    </div>
                                    <div className="flex items-center gap-1 text-sm text-gray-500">
                                        <CheckSquare className="w-4 h-4" /> {m.completed_task_count}/{m.task_count} tasks
                                    </div>
                                </div>
                                <div className="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                    <div className="h-full bg-primary-500 rounded-full transition-all" style={{ width: `${m.progress}%` }} />
                                </div>
                                <div className="text-xs text-gray-500 mt-1">{m.progress}% complete</div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowModal(false)}>
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-lg mx-4" onClick={(e) => e.stopPropagation()}>
                        <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">New Milestone</h2>
                            <button onClick={() => setShowModal(false)} className="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"><X className="w-5 h-5 text-gray-500" /></button>
                        </div>
                        <form onSubmit={handleCreate} className="p-6 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Name *</label>
                                <input type="text" required value={newMile.name} onChange={(e) => setNewMile(prev => ({ ...prev, name: e.target.value }))} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm" placeholder="Milestone name" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Project</label>
                                <select value={newMile.project_id} onChange={(e) => setNewMile(prev => ({ ...prev, project_id: parseInt(e.target.value) || 0 }))} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                                    <option value={0}>Select project</option>
                                    {projects.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Due Date</label>
                                <input type="date" value={newMile.due_date} onChange={(e) => setNewMile(prev => ({ ...prev, due_date: e.target.value }))} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <textarea value={newMile.description} onChange={(e) => setNewMile(prev => ({ ...prev, description: e.target.value }))} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm resize-none" rows={3} placeholder="Optional description" />
                            </div>
                            <div className="flex justify-end gap-3 pt-2">
                                <button type="button" onClick={() => setShowModal(false)} className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Cancel</button>
                                <button type="submit" className="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700">Create</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </DashboardLayout>
    );
}
