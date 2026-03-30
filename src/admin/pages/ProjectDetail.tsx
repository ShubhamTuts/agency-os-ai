import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPost, apiPut, apiDelete } from '../services/api';
import {
    ArrowLeft, Plus, Users, Calendar, CheckSquare, MessageSquare,
    Clock, Trash2, Edit2, X, GripVertical, Sparkles, Save
} from 'lucide-react';
import { DndContext, closestCenter, DragEndEvent, PointerSensor, useDroppable, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, horizontalListSortingStrategy, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';

interface Task {
    id: number;
    title: string;
    description: string;
    status: string;
    priority: string;
    assignee_id: number;
    assignee_name: string;
    due_date: string;
    estimated_hours: number;
    task_list_id: number;
    task_list_name: string;
    position: number;
}

interface TaskList {
    id: number;
    name: string;
    position: number;
    task_count: number;
}

interface Member {
    id: number;
    name: string;
    email: string;
    avatar?: string;
}

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
    members: Member[];
}

const COLUMNS = ['backlog', 'todo', 'in_progress', 'in_review', 'completed'];

const columnLabels: Record<string, string> = {
    backlog: 'Backlog',
    todo: 'To Do',
    in_progress: 'In Progress',
    in_review: 'In Review',
    completed: 'Completed',
};

const priorityColors: Record<string, string> = {
    high: 'border-l-red-500',
    medium: 'border-l-yellow-500',
    low: 'border-l-gray-400',
    urgent: 'border-l-red-700',
};

function SortableTask({ task, onClick }: { task: Task; onClick: () => void }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: task.id });
    const style = {
        transform: CSS.Transform.toString(transform),
        transition,
        opacity: isDragging ? 0.5 : 1,
    };
    return (
        <div
            ref={setNodeRef}
            style={style}
            {...attributes}
            {...listeners}
            onClick={onClick}
            className={`bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-3 cursor-pointer hover:shadow-md transition-shadow border-l-4 ${priorityColors[task.priority] || 'border-l-gray-400'}`}
        >
            <p className="text-sm font-medium text-gray-900 dark:text-white mb-2 line-clamp-2">{task.title}</p>
            <div className="flex items-center justify-between text-xs text-gray-500">
                <div className="flex items-center gap-2">
                    {task.task_list_name && <span className="px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-xs">{task.task_list_name}</span>}
                    {task.due_date && (
                        <span className="flex items-center gap-0.5"><Calendar className="w-3 h-3" />{new Date(task.due_date).toLocaleDateString()}</span>
                    )}
                </div>
                {task.assignee_name && <span className="font-medium">{task.assignee_name}</span>}
            </div>
        </div>
    );
}

function DroppableColumn({
    status,
    count,
    children,
}: {
    status: string;
    count: number;
    children: React.ReactNode;
}) {
    const { setNodeRef, isOver } = useDroppable({ id: `col-${status}` });

    return (
        <div className="flex-shrink-0 w-72">
            <div
                ref={setNodeRef}
                className={`bg-gray-100 dark:bg-gray-800/50 rounded-xl p-3 transition-colors ${isOver ? 'ring-2 ring-primary-400 bg-primary-50/60 dark:bg-primary-900/10' : ''}`}
            >
                <div className="flex items-center justify-between mb-3">
                    <div className="flex items-center gap-2">
                        <div className={`w-2 h-2 rounded-full ${status === 'completed' ? 'bg-green-500' : status === 'in_progress' ? 'bg-blue-500' : 'bg-gray-400'}`} />
                        <h3 className="font-semibold text-sm text-gray-700 dark:text-gray-300">{columnLabels[status]}</h3>
                        <span className="text-xs text-gray-400 bg-white dark:bg-gray-700 px-1.5 py-0.5 rounded-full">{count}</span>
                    </div>
                </div>
                {children}
            </div>
        </div>
    );
}

export default function ProjectDetail() {
    const { id } = useParams<{ id: string }>();
    const navigate = useNavigate();
    const [project, setProject] = useState<Project | null>(null);
    const [tasks, setTasks] = useState<Task[]>([]);
    const [taskLists, setTaskLists] = useState<TaskList[]>([]);
    const [loading, setLoading] = useState(true);
    const [view, setView] = useState<'kanban' | 'list'>('kanban');
    const [showTaskModal, setShowTaskModal] = useState(false);
    const [selectedTask, setSelectedTask] = useState<Task | null>(null);
    const [newTask, setNewTask] = useState({ title: '', description: '', priority: 'medium', status: 'backlog', assignee_id: 0, due_date: '', estimated_hours: 0, task_list_id: 0 });
    const [showMembers, setShowMembers] = useState(false);
    const [newListName, setNewListName] = useState('');
    const [aiGenerating, setAiGenerating] = useState(false);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }));

    useEffect(() => { loadData(); }, [id]);

    async function loadData() {
        setLoading(true);
        try {
            const [p, t, l] = await Promise.all([
                apiGet<Project>(`/aosai/v1/projects/${id}`),
                apiGet<Task[]>(`/aosai/v1/projects/${id}/tasks`),
                apiGet<TaskList[]>(`/aosai/v1/projects/${id}/task-lists`),
            ]);
            setProject((p && !Array.isArray(p)) ? p : null);
            setTasks(Array.isArray(t) ? t : []);
            setTaskLists(Array.isArray(l) ? l : []);
        } catch { navigate('/projects'); }
        finally { setLoading(false); }
    }

    function handleDragEnd(event: DragEndEvent) {
        const { active, over } = event;
        if (!over || active.id === over.id) return;
        const taskId = Number(active.id);
        const task = tasks.find(t => t.id === taskId);
        if (!task) return;
        if (String(over.id).startsWith('col-')) {
            const newStatus = String(over.id).replace('col-', '');
            apiPut(`/aosai/v1/tasks/${taskId}`, { status: newStatus }).then(() => {
                setTasks(prev => prev.map(t => t.id === taskId ? { ...t, status: newStatus } : t));
            }).catch(console.error);
        } else {
            const overTask = tasks.find(t => t.id === Number(over.id));
            if (overTask) {
                const newPos = overTask.position;
                const payload: Partial<Task> = { position: newPos };
                if (overTask.status !== task.status) {
                    payload.status = overTask.status;
                }
                apiPut(`/aosai/v1/tasks/${taskId}`, payload).then(() => {
                    setTasks(prev => prev.map(t => t.id === taskId ? { ...t, ...payload } : t));
                }).catch(console.error);
            }
        }
    }

    async function handleCreateTask(e: React.FormEvent) {
        e.preventDefault();
        if (!newTask.title.trim()) return;
        try {
            const res = await apiPost<Task>(`/aosai/v1/projects/${id}/tasks`, { ...newTask, project_id: Number(id) });
            setTasks(prev => [...prev, res]);
            setShowTaskModal(false);
            setNewTask({ title: '', description: '', priority: 'medium', status: 'backlog', assignee_id: 0, due_date: '', estimated_hours: 0, task_list_id: 0 });
        } catch (err: any) { alert(err.message); }
    }

    async function handleAiGenerate() {
        if (!project) return;
        setAiGenerating(true);
        try {
            const res = await apiPost<{ task_lists?: Array<{ title: string; tasks?: Array<Partial<Task>> }> }>(`/aosai/v1/ai/generate-tasks`, {
                project_id: Number(id),
                project_title: project.name,
                project_description: project.description,
            });
            const generatedLists = Array.isArray(res?.task_lists) ? res.task_lists : [];
            const createdLists: TaskList[] = [];
            const createdTasks: Task[] = [];

            for (const list of generatedLists) {
                const createdList = await apiPost<TaskList>(`/aosai/v1/projects/${id}/task-lists`, { title: list.title });
                createdLists.push(createdList);

                for (const taskData of list.tasks ?? []) {
                    const createdTask = await apiPost<Task>(`/aosai/v1/projects/${id}/tasks`, {
                        ...taskData,
                        project_id: Number(id),
                        task_list_id: createdList.id,
                        status: taskData.status || 'backlog',
                    });
                    createdTasks.push(createdTask);
                }
            }

            if (createdLists.length > 0) {
                setTaskLists(prev => [...prev, ...createdLists]);
            }
            if (createdTasks.length > 0) {
                setTasks(prev => [...prev, ...createdTasks]);
            }
        } catch (err: any) { alert(err.message); }
        finally { setAiGenerating(false); }
    }

    async function handleAddList(e: React.FormEvent) {
        e.preventDefault();
        if (!newListName.trim()) return;
        try {
            const res = await apiPost<TaskList>(`/aosai/v1/projects/${id}/task-lists`, { name: newListName });
            setTaskLists(prev => [...prev, res]);
            setNewListName('');
        } catch (err: any) { alert(err.message); }
    }

    async function handleUpdateTask(taskId: number, data: Partial<Task>) {
        try {
            const res = await apiPut<Task>(`/aosai/v1/tasks/${taskId}`, data);
            setTasks(prev => prev.map(t => t.id === taskId ? { ...t, ...res } : t));
            setSelectedTask(null);
            setShowTaskModal(false);
        } catch (err: any) { alert(err.message); }
    }

    async function handleDeleteTask(taskId: number) {
        if (!confirm('Delete this task?')) return;
        try {
            await apiDelete(`/aosai/v1/tasks/${taskId}`);
            setTasks(prev => prev.filter(t => t.id !== taskId));
            setSelectedTask(null);
            setShowTaskModal(false);
        } catch (err: any) { alert(err.message); }
    }

    if (loading) return <DashboardLayout><LoadingSpinner /></DashboardLayout>;
    if (!project) return null;

    const tasksByStatus = (status: string) =>
        tasks
            .filter(t => t.status === status)
            .sort((a, b) => (a.position ?? 0) - (b.position ?? 0));

    return (
        <DashboardLayout>
            <div className="max-w-full mx-auto space-y-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <button onClick={() => navigate('/projects')} className="p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500">
                            <ArrowLeft className="w-5 h-5" />
                        </button>
                        <div>
                            <div className="flex items-center gap-2">
                                <div className="w-3 h-3 rounded-full" style={{ backgroundColor: project.color }} />
                                <h1 className="text-xl font-bold text-gray-900 dark:text-white">{project.name}</h1>
                            </div>
                            <p className="text-sm text-gray-500 mt-0.5">{project.description || 'No description'}</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button onClick={() => setShowMembers(true)} className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 text-gray-700 dark:text-gray-300">
                            <Users className="w-4 h-4" /> {project.member_count}
                        </button>
                        <button onClick={handleAiGenerate} disabled={aiGenerating} className="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm bg-purple-600 text-white rounded-lg hover:bg-purple-700 disabled:opacity-50">
                            <Sparkles className="w-4 h-4" /> {aiGenerating ? 'Generating...' : 'AI Generate Tasks'}
                        </button>
                        <button onClick={() => setShowTaskModal(true)} className="inline-flex items-center gap-1.5 px-3 py-1.5 bg-primary-600 text-white rounded-lg hover:bg-primary-700 text-sm">
                            <Plus className="w-4 h-4" /> Add Task
                        </button>
                    </div>
                </div>

                <div className="flex items-center gap-4">
                    <div className="flex-1 h-2 bg-gray-100 dark:bg-gray-800 rounded-full overflow-hidden">
                        <div className="h-full bg-primary-500 rounded-full transition-all" style={{ width: `${project.progress}%` }} />
                    </div>
                    <span className="text-sm text-gray-500">{project.progress}% complete</span>
                    <div className="flex gap-1">
                        <button onClick={() => setView('kanban')} className={`px-3 py-1 text-xs rounded-lg ${view === 'kanban' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}>Kanban</button>
                        <button onClick={() => setView('list')} className={`px-3 py-1 text-xs rounded-lg ${view === 'list' ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400' : 'text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800'}`}>List</button>
                    </div>
                </div>

                {view === 'kanban' ? (
                    <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
                        <div className="flex gap-4 overflow-x-auto pb-4" style={{ minHeight: '60vh' }}>
                            {COLUMNS.map((status) => (
                                <DroppableColumn key={status} status={status} count={tasksByStatus(status).length}>
                                        <SortableContext items={tasksByStatus(status).map(t => t.id)} strategy={verticalListSortingStrategy}>
                                            <div className="space-y-2 min-h-[100px]">
                                                {tasksByStatus(status).map((task) => (
                                                    <SortableTask key={task.id} task={task} onClick={() => { setSelectedTask(task); setShowTaskModal(true); }} />
                                                ))}
                                            </div>
                                        </SortableContext>
                                </DroppableColumn>
                            ))}
                        </div>
                    </DndContext>
                ) : (
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-gray-50 dark:bg-gray-800/50 border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Task</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Status</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Priority</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Assignee</th>
                                    <th className="text-left px-4 py-3 font-medium text-gray-600 dark:text-gray-400">Due Date</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-700">
                                {tasks.map((task) => (
                                    <tr key={task.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer" onClick={() => { setSelectedTask(task); setShowTaskModal(true); }}>
                                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-white">{task.title}</td>
                                        <td className="px-4 py-3"><span className={`text-xs px-2 py-1 rounded-full ${task.status === 'completed' ? 'bg-green-100 text-green-700' : task.status === 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'}`}>{columnLabels[task.status]}</span></td>
                                        <td className="px-4 py-3"><span className={`text-xs px-2 py-1 rounded-full ${task.priority === 'high' || task.priority === 'urgent' ? 'bg-red-100 text-red-700' : task.priority === 'medium' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600'}`}>{task.priority}</span></td>
                                        <td className="px-4 py-3 text-gray-500">{task.assignee_name || '-'}</td>
                                        <td className="px-4 py-3 text-gray-500">{task.due_date ? new Date(task.due_date).toLocaleDateString() : '-'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {showTaskModal && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => { setShowTaskModal(false); setSelectedTask(null); setNewTask({ title: '', description: '', priority: 'medium', status: 'backlog', assignee_id: 0, due_date: '', estimated_hours: 0, task_list_id: 0 }); }}>
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto" onClick={(e) => e.stopPropagation()}>
                        <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">{selectedTask ? 'Edit Task' : 'New Task'}</h2>
                            <button onClick={() => { setShowTaskModal(false); setSelectedTask(null); }} className="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"><X className="w-5 h-5 text-gray-500" /></button>
                        </div>
                        <form onSubmit={selectedTask ? (e) => { e.preventDefault(); handleUpdateTask(selectedTask.id, selectedTask); } : handleCreateTask} className="p-6 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Title *</label>
                                <input type="text" required value={selectedTask ? selectedTask.title : newTask.title} onChange={(e) => selectedTask ? setSelectedTask({ ...selectedTask, title: e.target.value }) : setNewTask(prev => ({ ...prev, title: e.target.value }))} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <textarea value={selectedTask ? selectedTask.description : newTask.description} onChange={(e) => selectedTask ? setSelectedTask({ ...selectedTask, description: e.target.value }) : setNewTask(prev => ({ ...prev, description: e.target.value }))} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 resize-none" rows={4} />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                                    <select value={selectedTask ? selectedTask.status : newTask.status} onChange={(e) => selectedTask ? setSelectedTask({ ...selectedTask, status: e.target.value }) : setNewTask(prev => ({ ...prev, status: e.target.value }))} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500">
                                        {COLUMNS.map(c => <option key={c} value={c}>{columnLabels[c]}</option>)}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Priority</label>
                                    <select value={selectedTask ? selectedTask.priority : newTask.priority} onChange={(e) => selectedTask ? setSelectedTask({ ...selectedTask, priority: e.target.value }) : setNewTask(prev => ({ ...prev, priority: e.target.value }))} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500">
                                        <option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option>
                                    </select>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Due Date</label>
                                    <input type="date" value={selectedTask ? selectedTask.due_date : newTask.due_date} onChange={(e) => selectedTask ? setSelectedTask({ ...selectedTask, due_date: e.target.value }) : setNewTask(prev => ({ ...prev, due_date: e.target.value }))} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Task List</label>
                                    <select value={selectedTask ? selectedTask.task_list_id : newTask.task_list_id} onChange={(e) => selectedTask ? setSelectedTask({ ...selectedTask, task_list_id: parseInt(e.target.value, 10) || 0 }) : setNewTask(prev => ({ ...prev, task_list_id: parseInt(e.target.value, 10) || 0 }))} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500">
                                        <option value={0}>No task list</option>
                                        {taskLists.map((list) => <option key={list.id} value={list.id}>{list.name}</option>)}
                                    </select>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Est. Hours</label>
                                    <input type="number" min="0" step="0.5" value={selectedTask ? selectedTask.estimated_hours : newTask.estimated_hours} onChange={(e) => selectedTask ? setSelectedTask({ ...selectedTask, estimated_hours: parseFloat(e.target.value) || 0 }) : setNewTask(prev => ({ ...prev, estimated_hours: parseFloat(e.target.value) || 0 }))} className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500" />
                                </div>
                            </div>
                            <div className="flex items-center justify-end gap-3 pt-2">
                                {selectedTask && (
                                    <button type="button" onClick={() => handleDeleteTask(selectedTask.id)} className="px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg">Delete</button>
                                )}
                                <button type="button" onClick={() => { setShowTaskModal(false); setSelectedTask(null); }} className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Cancel</button>
                                <button type="submit" className="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700">{selectedTask ? 'Save Changes' : 'Create Task'}</button>
                            </div>
                        </form>
                    </div>
                </div>
            )}

            {showMembers && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowMembers(false)}>
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md mx-4" onClick={(e) => e.stopPropagation()}>
                        <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Team Members</h2>
                            <button onClick={() => setShowMembers(false)} className="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700"><X className="w-5 h-5 text-gray-500" /></button>
                        </div>
                        <div className="p-6 space-y-3">
                            {project.members?.map((m) => (
                                <div key={m.id} className="flex items-center gap-3">
                                    <div className="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 flex items-center justify-center text-sm font-medium">{m.name?.charAt(0) || '?'}</div>
                                    <div>
                                        <p className="text-sm font-medium text-gray-900 dark:text-white">{m.name}</p>
                                        <p className="text-xs text-gray-500">{m.email}</p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            )}
        </DashboardLayout>
    );
}
