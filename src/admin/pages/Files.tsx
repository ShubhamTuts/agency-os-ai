import React, { useState, useEffect } from 'react';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPostForm, apiDelete } from '../services/api';
import { Upload, Search, Trash2, FileText, Image, Film, Download, X } from 'lucide-react';

interface ManagedFile {
    id: number;
    filename: string;
    url: string;
    mime_type: string;
    file_size: number;
    project_name: string;
    uploaded_by_name: string;
    created_at: string;
}

interface ProjectOption {
    id: number;
    name: string;
}

export default function Files() {
    const [files, setFiles] = useState<ManagedFile[]>([]);
    const [projects, setProjects] = useState<ProjectOption[]>([]);
    const [loading, setLoading] = useState(true);
    const [search, setSearch] = useState('');
    const [uploading, setUploading] = useState(false);
    const [dragOver, setDragOver] = useState(false);
    const [selectedProjectId, setSelectedProjectId] = useState(0);

    useEffect(() => {
        apiGet<ProjectOption[]>('/aosai/v1/projects', { per_page: 100 }).then((res) => {
            const projectList = Array.isArray(res) ? res : [];
            setProjects(projectList);
            if (projectList.length > 0) {
                setSelectedProjectId(prev => prev || projectList[0].id);
            }
        }).catch(() => setProjects([]));
    }, []);

    useEffect(() => { loadFiles(); }, [search]);

    async function loadFiles() {
        setLoading(true);
        try {
            const params: Record<string, string> = {};
            if (search) params.search = search;
            const res = await apiGet<ManagedFile[]>('/aosai/v1/files', params);
            setFiles(Array.isArray(res) ? res : []);
        } catch { setFiles([]); }
        finally { setLoading(false); }
    }

    async function handleUpload(fileList: FileList) {
        if (!selectedProjectId) {
            alert('Select a project before uploading files.');
            return;
        }
        setUploading(true);
        try {
            for (const file of Array.from(fileList)) {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('project_id', String(selectedProjectId));
                const res = await apiPostForm<ManagedFile>('/aosai/v1/files', fd);
                setFiles(prev => [res, ...prev]);
            }
        } catch (err: any) { alert(err.message); }
        finally { setUploading(false); }
    }

    async function handleDelete(id: number) {
        if (!confirm('Delete this file?')) return;
        try {
            await apiDelete(`/aosai/v1/files/${id}`);
            setFiles(prev => prev.filter(f => f.id !== id));
        } catch (err: any) { alert(err.message); }
    }

    function formatSize(bytes: number) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function getIcon(mime: string) {
        if (mime.startsWith('image/')) return <Image className="w-8 h-8 text-green-500" />;
        if (mime.startsWith('video/')) return <Film className="w-8 h-8 text-purple-500" />;
        return <FileText className="w-8 h-8 text-blue-500" />;
    }

    return (
        <DashboardLayout>
            <div className="max-w-6xl mx-auto space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Files</h1>
                        <p className="text-sm text-gray-500 mt-1">Manage project files and attachments</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <select value={selectedProjectId} onChange={(e) => setSelectedProjectId(parseInt(e.target.value, 10) || 0)} className="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm min-w-[220px]">
                            <option value={0}>Select upload project</option>
                            {projects.map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}
                        </select>
                        <label className={`inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium cursor-pointer ${selectedProjectId ? 'bg-primary-600 text-white hover:bg-primary-700' : 'bg-gray-200 text-gray-500 cursor-not-allowed dark:bg-gray-700 dark:text-gray-400'}`}>
                            <Upload className="w-4 h-4" /> Upload
                            <input type="file" multiple className="hidden" disabled={!selectedProjectId} onChange={(e) => e.target.files && handleUpload(e.target.files)} />
                        </label>
                    </div>
                </div>

                <div
                    className={`border-2 border-dashed rounded-xl p-8 text-center transition-colors ${dragOver ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/10' : 'border-gray-300 dark:border-gray-600'}`}
                    onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
                    onDragLeave={() => setDragOver(false)}
                    onDrop={(e) => { e.preventDefault(); setDragOver(false); if (e.dataTransfer.files) handleUpload(e.dataTransfer.files); }}
                >
                    <Upload className="w-8 h-8 mx-auto text-gray-400 mb-2" />
                    <p className="text-sm text-gray-500">Drag and drop files here, or click Upload</p>
                    <p className="text-xs text-gray-400 mt-1">{selectedProjectId ? `Uploading into ${projects.find(project => project.id === selectedProjectId)?.name || 'selected project'}` : 'Choose a project first'}</p>
                    {uploading && <p className="text-xs text-primary-600 mt-1">Uploading...</p>}
                </div>

                <div className="relative">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                    <input type="text" placeholder="Search files..." value={search} onChange={(e) => setSearch(e.target.value)} className="w-full pl-9 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm" />
                </div>

                {loading ? <LoadingSpinner /> : files.length === 0 ? (
                    <div className="text-center py-12 text-sm text-gray-500">No files found</div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        {files.map((f) => (
                            <div key={f.id} className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4 hover:shadow-md transition-shadow">
                                <div className="flex items-start justify-between mb-3">
                                    {getIcon(f.mime_type)}
                                    <div className="flex items-center gap-1">
                                        <a href={f.url} download={f.filename} target="_blank" rel="noopener noreferrer" className="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-400">
                                            <Download className="w-4 h-4" />
                                        </a>
                                        <button onClick={() => handleDelete(f.id)} className="p-1 rounded hover:bg-red-50 dark:hover:bg-red-900/20 text-gray-400 hover:text-red-600">
                                            <Trash2 className="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                                <h4 className="text-sm font-medium text-gray-900 dark:text-white truncate mb-1">{f.filename}</h4>
                                <div className="text-xs text-gray-500 space-y-0.5">
                                    <p>{formatSize(f.file_size)}</p>
                                    <p>{f.project_name || 'General'}</p>
                                    <p>{f.uploaded_by_name} &middot; {new Date(f.created_at).toLocaleDateString()}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </DashboardLayout>
    );
}
