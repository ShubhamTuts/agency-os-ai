import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPost, apiDelete } from '../services/api';
import { Plus, Search, MoreHorizontal, Calendar, Users, CheckSquare, Trash2, Edit2 } from 'lucide-react';

interface Project {
    id: number;
    name: string;
    description: string;
    status: string;
    color: string;
    start_date: string;
    due_date: string;
    owner_name: string;
    member_count: number;
    task_count: number;
    completed_task_count: number;
    progress: number;
    created_at: string;
}

export default function Projects() {
    const navigate = useNavigate();
    const [projects, setProjects] = useState<Project[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [statusFilter, setStatusFilter] = useState('');
    const [showModal, setShowModal] = useState(false);
    const [newProject, setNewProject] = useState({ name: '', description: '', start_date: '', due_date: '', color: '#6366f1' });
    const [creating, setCreating] = useState(false);
    const [menuOpen, setMenuOpen] = useState<number | null>(null);

    useEffect(() => {
        loadProjects();
    }, [search, statusFilter]);

    async function loadProjects() {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;
            const res = await apiGet<Project[]>('/aosai/v1/projects', params);
            setProjects(Array.isArray(res) ? res : []);
        } catch {
            setProjects([]);
        } finally {
            setLoading(false);
        }
    }

    async function handleCreate(e: React.FormEvent) {
        e.preventDefault();
        if (!newProject.name.trim()) return;
        setCreating(true);
        try {
            const res = await apiPost<Project>('/aosai/v1/projects', newProject);
            setProjects(prev => [res, ...prev]);
            setShowModal(false);
            setNewProject({ name: '', description: '', start_date: '', due_date: '', color: '#6366f1' });
            navigate(`/projects/${res.id}`);
        } catch (err: any) {
            alert(err.message);
        } finally {
            setCreating(false);
        }
    }

    async function handleDelete(id: number) {
        if (!confirm('Delete this project? This cannot be undone.')) return;
        try {
            await apiDelete(`/aosai/v1/projects/${id}`);
            setProjects(prev => prev.filter(p => p.id !== id));
        } catch (err: any) {
            alert(err.message);
        }
        setMenuOpen(null);
    }

    const statusColors: Record<string, string> = {
        active: 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        completed: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
        on_hold: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
        archived: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    };

    return (
        <DashboardLayout>
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Projects</h1>
                        <p className="text-sm text-gray-500 mt-1">Manage all your projects</p>
                    </div>
                    <button
                        onClick={() => setShowModal(true)}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm font-medium"
                    >
                        <Plus className="w-4 h-4" /> New Project
                    </button>
                </div>

                <div className="flex items-center gap-3">
                    <div className="relative flex-1 max-w-md">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                            type="text"
                            placeholder="Search projects..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full pl-9 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                        />
                    </div>
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                    >
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="on_hold">On Hold</option>
                        <option value="completed">Completed</option>
                        <option value="archived">Archived</option>
                    </select>
                </div>

                {loading ? (
                    <LoadingSpinner />
                ) : projects.length === 0 ? (
                    <div className="text-center py-16">
                        <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <CheckSquare className="w-8 h-8 text-gray-400" />
                        </div>
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-1">No projects found</h3>
                        <p className="text-sm text-gray-500 mb-4">Create your first project to get started</p>
                        <button onClick={() => setShowModal(true)} className="inline-flex items-center gap-2 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm">
                            <Plus className="w-4 h-4" /> New Project
                        </button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        {projects.map((project) => (
                            <div key={project.id} className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden hover:shadow-md transition-shadow">
                                <div className="h-2" style={{ backgroundColor: project.color || '#6366f1' }} />
                                <div className="p-5">
                                    <div className="flex items-start justify-between mb-3">
                                        <div className="flex-1 min-w-0 cursor-pointer" onClick={() => navigate(`/projects/${project.id}`)}>
                                            <h3 className="font-semibold text-gray-900 dark:text-white truncate">{project.name}</h3>
                                            <p className="text-xs text-gray-500 mt-1 line-clamp-2">{project.description || 'No description'}</p>
                                        </div>
                                        <div className="relative">
                                            <button
                                                onClick={() => setMenuOpen(menuOpen === project.id ? null : project.id)}
                                                className="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400"
                                            >
                                                <MoreHorizontal className="w-4 h-4" />
                                            </button>
                                            {menuOpen === project.id && (
                                                <div className="absolute right-0 mt-1 w-40 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-10 py-1">
                                                    <button onClick={() => { navigate(`/projects/${project.id}`); setMenuOpen(null); }} className="w-full px-3 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2">
                                                        <Edit2 className="w-3 h-3" /> Edit
                                                    </button>
                                                    <button onClick={() => handleDelete(project.id)} className="w-full px-3 py-2 text-left text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 flex items-center gap-2">
                                                        <Trash2 className="w-3 h-3" /> Delete
                                                    </button>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-2 mb-3">
                                        <span className={`text-xs px-2 py-1 rounded-full font-medium ${statusColors[project.status] || statusColors.active}`}>
                                            {project.status?.replace('_', ' ')}
                                        </span>
                                    </div>

                                    <div className="mb-3">
                                        <div className="flex items-center justify-between text-xs text-gray-500 mb-1">
                                            <span>Progress</span>
                                            <span>{project.progress}%</span>
                                        </div>
                                        <div className="h-2 bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                                            <div
                                                className="h-full rounded-full transition-all"
                                                style={{ width: `${project.progress}%`, backgroundColor: project.color || '#6366f1' }}
                                            />
                                        </div>
                                    </div>

                                    <div className="flex items-center justify-between text-xs text-gray-500">
                                        <div className="flex items-center gap-3">
                                            <span className="flex items-center gap-1"><CheckSquare className="w-3 h-3" /> {project.completed_task_count}/{project.task_count}</span>
                                            <span className="flex items-center gap-1"><Users className="w-3 h-3" /> {project.member_count}</span>
                                        </div>
                                        {project.due_date && (
                                            <span className="flex items-center gap-1"><Calendar className="w-3 h-3" /> {new Date(project.due_date).toLocaleDateString()}</span>
                                        )}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

            {showModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowModal(false)}>
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-lg mx-4" onClick={(e) => e.stopPropagation()}>
                        <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">New Project</h2>
                        </div>
                        <form onSubmit={handleCreate} className="p-6 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Project Name *</label>
                                <input
                                    type="text"
                                    required
                                    value={newProject.name}
                                    onChange={(e) => setNewProject(prev => ({ ...prev, name: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    placeholder="Project name"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <textarea
                                    value={newProject.description}
                                    onChange={(e) => setNewProject(prev => ({ ...prev, description: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 resize-none"
                                    rows={3}
                                    placeholder="Optional description"
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                                    <input
                                        type="date"
                                        value={newProject.start_date}
                                        onChange={(e) => setNewProject(prev => ({ ...prev, start_date: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Due Date</label>
                                    <input
                                        type="date"
                                        value={newProject.due_date}
                                        onChange={(e) => setNewProject(prev => ({ ...prev, due_date: e.target.value }))}
                                        className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                    />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Color</label>
                                <div className="flex items-center gap-3">
                                    <input
                                        type="color"
                                        value={newProject.color}
                                        onChange={(e) => setNewProject(prev => ({ ...prev, color: e.target.value }))}
                                        className="w-10 h-10 rounded cursor-pointer border-0"
                                    />
                                    <span className="text-sm text-gray-500">{newProject.color}</span>
                                </div>
                            </div>
                            <div className="flex items-center justify-end gap-3 pt-2">
                                <button type="button" onClick={() => setShowModal(false)} className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Cancel</button>
                                <button type="submit" disabled={creating} className="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
                                    {creating ? 'Creating...' : 'Create Project'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </DashboardLayout>
    );
}
