import React, { useState, useEffect, useRef } from 'react';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPost, apiDelete } from '../services/api';
import { Plus, Play, Pause, Square, Trash2, Clock, Briefcase, CheckSquare, DollarSign } from 'lucide-react';

interface Project {
    id: number;
    name: string;
}

interface Task {
    id: number;
    title: string;
}

interface TimeEntry {
    id: number;
    user_id: number;
    user_name: string;
    project_id: number;
    project_name: string;
    task_id?: number;
    task_title?: string;
    description: string;
    start_time: string;
    end_time?: string;
    duration: number;
    billable: boolean;
    created_at: string;
    date?: string;
}

export default function TimeTracking() {
    const [entries, setEntries] = useState<TimeEntry[]>([]);
    const [projects, setProjects] = useState<Project[]>([]);
    const [loading, setLoading] = useState(true);
    const [dateFilter, setDateFilter] = useState('');
    const [projectFilter, setProjectFilter] = useState('');
    const [isTimerRunning, setIsTimerRunning] = useState(false);
    const [currentEntry, setCurrentEntry] = useState<{ project_id: string; description: string; billable: boolean }>({
        project_id: '',
        description: '',
        billable: true
    });
    const [timerDuration, setTimerDuration] = useState(0);
    const [startTime, setStartTime] = useState<Date | null>(null);
    const [showManual, setShowManual] = useState(false);
    const [manualEntry, setManualEntry] = useState({
        project_id: '',
        description: '',
        hours: '',
        minutes: '',
        date: new Date().toISOString().split('T')[0],
        billable: true
    });
    const [saving, setSaving] = useState(false);
    const timerRef = useRef<NodeJS.Timeout | null>(null);

    useEffect(() => {
        loadEntries();
        loadProjects();
        return () => {
            if (timerRef.current) clearInterval(timerRef.current);
        };
    }, [dateFilter, projectFilter]);

    async function loadEntries() {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (dateFilter) {
                params.date_from = dateFilter;
                params.date_to = dateFilter;
            }
            if (projectFilter) params.project_id = projectFilter;
            const res = await apiGet<TimeEntry[]>('/aosai/v1/time-entries', params);
            setEntries(Array.isArray(res) ? res : []);
        } catch {
            setEntries([]);
        } finally {
            setLoading(false);
        }
    }

    async function loadProjects() {
        try {
            const res = await apiGet<Project[]>('/aosai/v1/projects', { status: 'active' });
            setProjects(Array.isArray(res) ? res : []);
        } catch {
            setProjects([]);
        }
    }

    function startTimer() {
        if (!currentEntry.project_id) {
            alert('Please select a project');
            return;
        }
        setIsTimerRunning(true);
        setStartTime(new Date());
        setTimerDuration(0);
        timerRef.current = setInterval(() => {
            setTimerDuration(prev => prev + 1);
        }, 1000);
    }

    function pauseTimer() {
        if (timerRef.current) {
            clearInterval(timerRef.current);
            timerRef.current = null;
        }
        setIsTimerRunning(false);
    }

    function resumeTimer() {
        setIsTimerRunning(true);
        timerRef.current = setInterval(() => {
            setTimerDuration(prev => prev + 1);
        }, 1000);
    }

    async function stopTimer() {
        if (timerRef.current) {
            clearInterval(timerRef.current);
            timerRef.current = null;
        }
        setIsTimerRunning(false);
        
        if (!startTime || !currentEntry.project_id) return;
        
        const endTime = new Date();
        const duration = timerDuration / 3600;
        
        setSaving(true);
        try {
            const entry = {
                project_id: parseInt(currentEntry.project_id),
                description: currentEntry.description,
                start_time: startTime.toISOString(),
                end_time: endTime.toISOString(),
                duration: parseFloat(duration.toFixed(2)),
                billable: currentEntry.billable
            };
            const res = await apiPost<TimeEntry>('/aosai/v1/time-entries', entry);
            setEntries(prev => [res, ...prev]);
            setCurrentEntry({ project_id: '', description: '', billable: true });
            setTimerDuration(0);
            setStartTime(null);
        } catch (err: any) {
            alert(err.message);
        } finally {
            setSaving(false);
        }
    }

    async function discardTimer() {
        if (timerRef.current) {
            clearInterval(timerRef.current);
            timerRef.current = null;
        }
        setIsTimerRunning(false);
        setCurrentEntry({ project_id: '', description: '', billable: true });
        setTimerDuration(0);
        setStartTime(null);
    }

    async function handleManualEntry(e: React.FormEvent) {
        e.preventDefault();
        if (!manualEntry.project_id) {
            alert('Please select a project');
            return;
        }
        const hours = parseFloat(manualEntry.hours) || 0;
        const minutes = parseFloat(manualEntry.minutes) || 0;
        const duration = hours + (minutes / 60);
        
        if (duration <= 0) {
            alert('Please enter a valid duration');
            return;
        }
        
        setSaving(true);
        try {
            const entry = {
                project_id: parseInt(manualEntry.project_id),
                description: manualEntry.description,
                date: manualEntry.date,
                duration: parseFloat(duration.toFixed(2)),
                billable: manualEntry.billable
            };
            const res = await apiPost<TimeEntry>('/aosai/v1/time-entries', entry);
            setEntries(prev => [res, ...prev]);
            setShowManual(false);
            setManualEntry({ project_id: '', description: '', hours: '', minutes: '', date: new Date().toISOString().split('T')[0], billable: true });
        } catch (err: any) {
            alert(err.message);
        } finally {
            setSaving(false);
        }
    }

    async function handleDelete(id: number) {
        if (!confirm('Delete this time entry?')) return;
        try {
            await apiDelete(`/aosai/v1/time-entries/${id}`);
            setEntries(prev => prev.filter(e => e.id !== id));
        } catch (err: any) {
            alert(err.message);
        }
    }

    function formatDuration(seconds: number): string {
        const hrs = Math.floor(seconds / 3600);
        const mins = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return `${hrs.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }

    function formatHours(seconds: number): string {
        const hours = seconds / 3600;
        const h = Math.floor(hours);
        const m = Math.round((hours - h) * 60);
        return `${h}h ${m}m`;
    }

    function formatCurrency(amount: number): string {
        return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
    }

    const totalHours = entries.reduce((sum, e) => sum + e.duration, 0);
    const billableHours = entries.filter(e => e.billable).reduce((sum, e) => sum + e.duration, 0);

    return (
        <DashboardLayout>
            <div className="max-w-7xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Time Tracking</h1>
                        <p className="text-sm text-gray-500 mt-1">Track time spent on projects and tasks</p>
                    </div>
                    <button
                        onClick={() => setShowManual(true)}
                        className="inline-flex items-center gap-2 px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 text-sm font-medium"
                    >
                        <Plus className="w-4 h-4" /> Add Manual Entry
                    </button>
                </div>

                <div className="bg-gradient-to-r from-primary-600 to-primary-700 rounded-xl p-6 text-white shadow-lg">
                    <div className="flex items-center justify-between">
                        <div className="flex-1">
                            <h2 className="text-lg font-medium mb-4">Timer</h2>
                            <div className="flex items-center gap-4">
                                <div className="text-5xl font-mono font-bold">
                                    {formatDuration(timerDuration)}
                                </div>
                                {isTimerRunning && startTime && (
                                    <div className="text-sm opacity-80">
                                        Started at {startTime.toLocaleTimeString()}
                                    </div>
                                )}
                            </div>
                        </div>
                        <div className="flex items-center gap-4">
                            <select
                                value={currentEntry.project_id}
                                onChange={(e) => setCurrentEntry(prev => ({ ...prev, project_id: e.target.value }))}
                                disabled={isTimerRunning}
                                className="px-3 py-2 border border-white/30 rounded-lg bg-white/20 text-white placeholder-white/70 focus:ring-2 focus:ring-white/50"
                            >
                                <option value="" className="text-gray-900">Select Project</option>
                                {projects.map(p => (
                                    <option key={p.id} value={p.id} className="text-gray-900">{p.name}</option>
                                ))}
                            </select>
                            <input
                                type="text"
                                value={currentEntry.description}
                                onChange={(e) => setCurrentEntry(prev => ({ ...prev, description: e.target.value }))}
                                placeholder="What are you working on?"
                                className="px-3 py-2 border border-white/30 rounded-lg bg-white/20 text-white placeholder-white/70 focus:ring-2 focus:ring-white/50 min-w-64"
                            />
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={currentEntry.billable}
                                    onChange={(e) => setCurrentEntry(prev => ({ ...prev, billable: e.target.checked }))}
                                    className="rounded border-white/30"
                                />
                                Billable
                            </label>
                        </div>
                        <div className="flex items-center gap-2">
                            {!isTimerRunning && timerDuration === 0 && (
                                <button
                                    onClick={startTimer}
                                    disabled={!currentEntry.project_id}
                                    className="w-14 h-14 rounded-full bg-white text-primary-600 hover:bg-white/90 flex items-center justify-center shadow-lg disabled:opacity-50"
                                >
                                    <Play className="w-6 h-6 ml-1" />
                                </button>
                            )}
                            {isTimerRunning && (
                                <>
                                    <button
                                        onClick={pauseTimer}
                                        className="w-14 h-14 rounded-full bg-yellow-400 text-yellow-900 hover:bg-yellow-300 flex items-center justify-center shadow-lg"
                                    >
                                        <Pause className="w-6 h-6" />
                                    </button>
                                    <button
                                        onClick={stopTimer}
                                        disabled={saving}
                                        className="w-14 h-14 rounded-full bg-red-500 text-white hover:bg-red-400 flex items-center justify-center shadow-lg disabled:opacity-50"
                                    >
                                        <Square className="w-6 h-6" />
                                    </button>
                                </>
                            )}
                            {!isTimerRunning && timerDuration > 0 && (
                                <>
                                    <button
                                        onClick={resumeTimer}
                                        className="w-14 h-14 rounded-full bg-white text-primary-600 hover:bg-white/90 flex items-center justify-center shadow-lg"
                                    >
                                        <Play className="w-6 h-6 ml-1" />
                                    </button>
                                    <button
                                        onClick={discardTimer}
                                        className="w-14 h-14 rounded-full bg-red-500 text-white hover:bg-red-400 flex items-center justify-center shadow-lg"
                                    >
                                        <Trash2 className="w-5 h-5" />
                                    </button>
                                </>
                            )}
                        </div>
                    </div>
                </div>

                <div className="grid grid-cols-3 gap-4">
                    <div className="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-200 dark:border-gray-700">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                                <Clock className="w-5 h-5 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Total Hours</p>
                                <p className="text-xl font-semibold text-gray-900 dark:text-white">{formatHours(totalHours)}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-200 dark:border-gray-700">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-lg bg-green-100 dark:bg-green-900/30 flex items-center justify-center">
                                <DollarSign className="w-5 h-5 text-green-600" />
                            </div>
                            <div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Billable Hours</p>
                                <p className="text-xl font-semibold text-gray-900 dark:text-white">{formatHours(billableHours)}</p>
                            </div>
                        </div>
                    </div>
                    <div className="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-sm border border-gray-200 dark:border-gray-700">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 rounded-lg bg-purple-100 dark:bg-purple-900/30 flex items-center justify-center">
                                <CheckSquare className="w-5 h-5 text-purple-600" />
                            </div>
                            <div>
                                <p className="text-sm text-gray-500 dark:text-gray-400">Entries Today</p>
                                <p className="text-xl font-semibold text-gray-900 dark:text-white">{entries.filter(e => e.date === new Date().toISOString().split('T')[0]).length}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-3">
                    <div className="relative flex-1 max-w-xs">
                        <Clock className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                            type="date"
                            value={dateFilter}
                            onChange={(e) => setDateFilter(e.target.value)}
                            className="w-full pl-9 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                        />
                    </div>
                    <select
                        value={projectFilter}
                        onChange={(e) => setProjectFilter(e.target.value)}
                        className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                    >
                        <option value="">All Projects</option>
                        {projects.map(p => (
                            <option key={p.id} value={p.id}>{p.name}</option>
                        ))}
                    </select>
                </div>

                {loading ? (
                    <LoadingSpinner />
                ) : entries.length === 0 ? (
                    <div className="text-center py-16">
                        <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center">
                            <Clock className="w-8 h-8 text-gray-400" />
                        </div>
                        <h3 className="text-lg font-medium text-gray-900 dark:text-white mb-1">No time entries found</h3>
                        <p className="text-sm text-gray-500 mb-4">Start tracking your time with the timer above</p>
                    </div>
                ) : (
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <table className="w-full">
                            <thead className="bg-gray-50 dark:bg-gray-900/50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date & Time</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Project</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Billable</th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                {entries.map((entry) => (
                                    <tr key={entry.id} className="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td className="px-6 py-4">
                                            <div className="text-sm font-medium text-gray-900 dark:text-white">
                                                {new Date(entry.start_time).toLocaleDateString()}
                                            </div>
                                            <div className="text-xs text-gray-500">
                                                {new Date(entry.start_time).toLocaleTimeString()}
                                                {entry.end_time && ` - ${new Date(entry.end_time).toLocaleTimeString()}`}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="flex items-center gap-2">
                                                <Briefcase className="w-4 h-4 text-gray-400" />
                                                <span className="text-sm text-gray-900 dark:text-white">{entry.project_name}</span>
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400">
                                            {entry.description || '-'}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="text-sm font-medium text-gray-900 dark:text-white">
                                                {formatHours(entry.duration)}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4">
                                            {entry.billable ? (
                                                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                    Billable
                                                </span>
                                            ) : (
                                                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                    Non-billable
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-6 py-4 text-right">
                                            <button
                                                onClick={() => handleDelete(entry.id)}
                                                className="p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 text-red-500"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            {showManual && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => setShowManual(false)}>
                    <div className="bg-white dark:bg-gray-800 rounded-xl shadow-xl w-full max-w-md mx-4" onClick={(e) => e.stopPropagation()}>
                        <div className="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                            <h2 className="text-lg font-semibold text-gray-900 dark:text-white">Add Manual Time Entry</h2>
                        </div>
                        <form onSubmit={handleManualEntry} className="p-6 space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Project *</label>
                                <select
                                    required
                                    value={manualEntry.project_id}
                                    onChange={(e) => setManualEntry(prev => ({ ...prev, project_id: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                >
                                    <option value="">Select a project</option>
                                    {projects.map(p => (
                                        <option key={p.id} value={p.id}>{p.name}</option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date</label>
                                <input
                                    type="date"
                                    value={manualEntry.date}
                                    onChange={(e) => setManualEntry(prev => ({ ...prev, date: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Duration *</label>
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <input
                                            type="number"
                                            min="0"
                                            value={manualEntry.hours}
                                            onChange={(e) => setManualEntry(prev => ({ ...prev, hours: e.target.value }))}
                                            className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                            placeholder="Hours"
                                        />
                                    </div>
                                    <div>
                                        <input
                                            type="number"
                                            min="0"
                                            max="59"
                                            value={manualEntry.minutes}
                                            onChange={(e) => setManualEntry(prev => ({ ...prev, minutes: e.target.value }))}
                                            className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                                            placeholder="Minutes"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                <textarea
                                    value={manualEntry.description}
                                    onChange={(e) => setManualEntry(prev => ({ ...prev, description: e.target.value }))}
                                    className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500 resize-none"
                                    rows={2}
                                    placeholder="What did you work on?"
                                />
                            </div>
                            <div>
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={manualEntry.billable}
                                        onChange={(e) => setManualEntry(prev => ({ ...prev, billable: e.target.checked }))}
                                        className="rounded border-gray-300"
                                    />
                                    <span className="text-sm text-gray-700 dark:text-gray-300">Billable time</span>
                                </label>
                            </div>
                            <div className="flex items-center justify-end gap-3 pt-2">
                                <button type="button" onClick={() => setShowManual(false)} className="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">Cancel</button>
                                <button type="submit" disabled={saving} className="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
                                    {saving ? 'Saving...' : 'Save Entry'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </DashboardLayout>
    );
}
