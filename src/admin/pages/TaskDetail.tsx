import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPost, apiPut, apiDelete } from '../services/api';
import { ArrowLeft, Calendar, User, Flag, Clock, MessageSquare, Paperclip, Send, Trash2, Save, Sparkles } from 'lucide-react';

interface Task {
    id: number;
    title: string;
    description: string;
    status: string;
    priority: string;
    project_id: number;
    project_name: string;
    assignee_id: number;
    assignee_name: string;
    due_date: string;
    estimated_hours: number;
    task_list_name: string;
    created_at: string;
    updated_at: string;
}

interface Comment {
    id: number;
    content: string;
    user_name: string;
    user_id: number;
    created_at: string;
    replies?: Comment[];
}

const STATUS_LABELS: Record<string, string> = {
    backlog: 'Backlog', todo: 'To Do', in_progress: 'In Progress', in_review: 'In Review', completed: 'Completed'
};

export default function TaskDetail() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const [task, setTask] = useState<Task | null>(null);
    const [comments, setComments] = useState<Comment[]>([]);
    const [loading, setLoading] = useState(true);
    const [newComment, setNewComment] = useState('');
    const [posting, setPosting] = useState(false);
    const [saving, setSaving] = useState(false);
    const [activeTab, setActiveTab] = useState<'comments' | 'activity'>('comments');

    useEffect(() => { loadTask(); }, [id]);

    async function loadTask() {
        setLoading(true);
        try {
            const [t, c] = await Promise.all([
                apiGet<Task>(`/aosai/v1/tasks/${id}`),
                apiGet<Comment[]>(`/aosai/v1/comments`, { task_id: id }),
            ]);
            setTask(t && !Array.isArray(t) ? t : null);
            setComments(Array.isArray(c) ? c : []);
        } catch { navigate('/'); }
        finally { setLoading(false); }
    }

    async function handleSave(field: string, value: any) {
        if (!task) return;
        setSaving(true);
        try {
            const updated = await apiPut<Task>(`/aosai/v1/tasks/${id}`, { [field]: value });
            setTask(updated);
        } catch (err: any) { alert(err.message); }
        finally { setSaving(false); }
    }

    async function handleAddComment(e: React.FormEvent) {
        e.preventDefault();
        if (!newComment.trim()) return;
        setPosting(true);
        try {
            const res = await apiPost<Comment>('/aosai/v1/comments', { task_id: Number(id), content: newComment });
            setComments(prev => [...prev, res]);
            setNewComment('');
        } catch (err: any) { alert(err.message); }
        finally { setPosting(false); }
    }

    async function handleAiSuggest() {
        if (!task?.description) return;
        try {
            const res = await apiPost<{ description?: string; suggestion?: string }>('/aosai/v1/ai/suggest-description', { title: task.title });
            const suggestion = res?.description || res?.suggestion || '';
            if (suggestion) {
                setTask(prev => prev ? { ...prev, description: suggestion } : prev);
            }
        } catch (err: any) { alert(err.message); }
    }

    if (loading) return <DashboardLayout><LoadingSpinner /></DashboardLayout>;
    if (!task) return null;

    const statusColors: Record<string, string> = {
        backlog: 'bg-gray-100 text-gray-600', todo: 'bg-blue-100 text-blue-700',
        in_progress: 'bg-yellow-100 text-yellow-700', in_review: 'bg-purple-100 text-purple-700', completed: 'bg-green-100 text-green-700'
    };
    const priorityColors: Record<string, string> = {
        low: 'bg-gray-100 text-gray-600', medium: 'bg-yellow-100 text-yellow-700', high: 'bg-red-100 text-red-700', urgent: 'bg-red-700 text-white'
    };

    return (
        <DashboardLayout>
            <div className="max-w-5xl mx-auto space-y-6">
                <div className="flex items-center gap-3">
                    <button onClick={() => navigate(-1)} className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500">
                        <ArrowLeft className="w-5 h-5" />
                    </button>
                    <div className="flex-1">
                        <div className="flex items-center gap-2 text-sm text-gray-500 mb-1">
                            <span className="cursor-pointer hover:text-primary-600" onClick={() => navigate(`/projects/${task.project_id}`)}>{task.project_name}</span>
                            {task.task_list_name && <><span>/</span><span>{task.task_list_name}</span></>}
                        </div>
                        <h1 className="text-xl font-bold text-gray-900 dark:text-white">{task.title}</h1>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div className="lg:col-span-2 space-y-6">
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h2 className="font-semibold text-gray-900 dark:text-white">Description</h2>
                                <button onClick={handleAiSuggest} className="inline-flex items-center gap-1 text-xs text-purple-600 hover:text-purple-700">
                                    <Sparkles className="w-3 h-3" /> AI Suggest
                                </button>
                            </div>
                            <textarea
                                value={task.description}
                                onChange={(e) => setTask(prev => prev ? { ...prev, description: e.target.value } : prev)}
                                onBlur={(e) => handleSave('description', e.target.value)}
                                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 resize-none"
                                rows={6}
                                placeholder="Add a description..."
                            />
                            {saving && <p className="text-xs text-gray-500 mt-2 flex items-center gap-1"><Clock className="w-3 h-3" /> Saving...</p>}
                        </div>

                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                            <div className="flex border-b border-gray-200 dark:border-gray-700">
                                <button onClick={() => setActiveTab('comments')} className={`px-4 py-3 text-sm font-medium ${activeTab === 'comments' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500 hover:text-gray-700'}`}>
                                    Comments ({comments.length})
                                </button>
                                <button onClick={() => setActiveTab('activity')} className={`px-4 py-3 text-sm font-medium ${activeTab === 'activity' ? 'text-primary-600 border-b-2 border-primary-600' : 'text-gray-500 hover:text-gray-700'}`}>
                                    Activity
                                </button>
                            </div>
                            <div className="p-6">
                                {activeTab === 'comments' ? (
                                    <div className="space-y-4">
                                        {comments.map((c) => (
                                            <div key={c.id} className="flex gap-3">
                                                <div className="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 flex items-center justify-center text-sm font-medium flex-shrink-0">
                                                    {c.user_name?.charAt(0) || '?'}
                                                </div>
                                                <div className="flex-1">
                                                    <div className="flex items-center gap-2 mb-1">
                                                        <span className="text-sm font-medium text-gray-900 dark:text-white">{c.user_name}</span>
                                                        <span className="text-xs text-gray-400">{new Date(c.created_at).toLocaleString()}</span>
                                                    </div>
                                                    <p className="text-sm text-gray-700 dark:text-gray-300">{c.content}</p>
                                                </div>
                                            </div>
                                        ))}
                                        <form onSubmit={handleAddComment} className="flex gap-3 pt-2">
                                            <div className="w-8 h-8 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center text-sm font-medium flex-shrink-0 text-gray-500">Y</div>
                                            <div className="flex-1 flex gap-2">
                                                <input
                                                    type="text"
                                                    value={newComment}
                                                    onChange={(e) => setNewComment(e.target.value)}
                                                    placeholder="Write a comment..."
                                                    className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                                />
                                                <button type="submit" disabled={posting} className="px-3 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
                                                    <Send className="w-4 h-4" />
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                ) : (
                                    <div className="text-sm text-gray-500 text-center py-8">Activity feed coming soon</div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                            <h3 className="font-semibold text-gray-900 dark:text-white text-sm">Details</h3>
                            <div>
                                <label className="text-xs text-gray-500 mb-1 block">Status</label>
                                <select value={task.status} onChange={(e) => handleSave('status', e.target.value)} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500">
                                    {Object.entries(STATUS_LABELS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
                                </select>
                            </div>
                            <div>
                                <label className="text-xs text-gray-500 mb-1 block">Priority</label>
                                <select value={task.priority} onChange={(e) => handleSave('priority', e.target.value)} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500">
                                    <option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label className="text-xs text-gray-500 mb-1 block">Due Date</label>
                                <input type="date" value={task.due_date || ''} onChange={(e) => handleSave('due_date', e.target.value)} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500" />
                            </div>
                            <div>
                                <label className="text-xs text-gray-500 mb-1 block">Estimated Hours</label>
                                <input type="number" min="0" step="0.5" value={task.estimated_hours || ''} onChange={(e) => handleSave('estimated_hours', parseFloat(e.target.value) || 0)} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500" />
                            </div>
                        </div>

                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 space-y-4">
                            <h3 className="font-semibold text-gray-900 dark:text-white text-sm">People</h3>
                            <div>
                                <label className="text-xs text-gray-500 mb-1 block">Assignee</label>
                                <div className="flex items-center gap-2 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700">
                                    <div className="w-6 h-6 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 flex items-center justify-center text-xs font-medium">
                                        {task.assignee_name?.charAt(0) || '?'}
                                    </div>
                                    <span className="text-sm text-gray-900 dark:text-white">{task.assignee_name || 'Unassigned'}</span>
                                </div>
                            </div>
                        </div>

                        <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                            <div className="text-xs text-gray-500 space-y-1">
                                <p>Created: {new Date(task.created_at).toLocaleString()}</p>
                                <p>Updated: {new Date(task.updated_at).toLocaleString()}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
