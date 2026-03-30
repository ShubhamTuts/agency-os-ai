import React, { useEffect, useMemo, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
    Briefcase,
    ChevronRight,
    Files,
    FolderKanban,
    LayoutDashboard,
    LifeBuoy,
    Loader2,
    LogOut,
    Mail,
    Save,
    Send,
    UserCircle2,
    ClipboardList,
} from 'lucide-react';
import '../admin/styles/index.css';
import { apiGet, apiPost, apiPut } from '../admin/services/api';
import { getAnimatedBrandLogo, getBrandLogo } from '../admin/utils/branding';

declare global {
    interface Window {
        aosaiPortalData?: {
            initialView?: string;
            portalUrl?: string;
            ticketUrl?: string;
            loginUrl?: string;
            logoutUrl?: string;
            lostPasswordUrl?: string;
            serviceWorkerUrl?: string;
            manifestUrl?: string;
            branding?: Branding;
            brandingAssets?: {
                logo?: string;
                animatedLogo?: string;
            };
        };
    }
}

interface Tag { id: number; name: string; color: string; }
interface UserProfile { id: number; first_name?: string; last_name?: string; display_name?: string; name?: string; email: string; avatar_url?: string; roles?: string[]; portal_type?: string; }
interface Project { id: number; name: string; description?: string; status?: string; progress?: number; due_date?: string; owner_name?: string; member_count?: number; tags?: Tag[]; }
interface Task { id: number; title: string; status: string; priority: string; due_date?: string; project_name?: string; task_list_name?: string; tags?: Tag[]; }
interface TicketNote { id: number; content: string; author_name?: string; created_at?: string; }
interface Ticket { id: number; subject: string; content: string; status: string; priority: string; project_name?: string; department_name?: string; department_id?: number; assignee_name?: string; tags?: Tag[]; notes?: TicketNote[]; notes_count?: number; created_at?: string; }
interface MessageItem { id: number; title?: string; content: string; project_name?: string; author_name?: string; created_at?: string; }
interface FileItem { id: number; filename: string; url: string; project_name?: string; uploaded_by_name?: string; created_at?: string; }
interface Department { id: number; name: string; color: string; description?: string; }
interface Branding {
    company_name: string;
    company_logo_url?: string;
    company_website?: string;
    support_email?: string;
    privacy_policy_url?: string;
    terms_url?: string;
    portal_name: string;
    welcome_title: string;
    welcome_text: string;
    primary_color: string;
    secondary_color: string;
    enable_pwa?: boolean;
    show_footer_credit?: boolean;
    footer_credit_text?: string;
}
interface NavItem { id: string; label: string; }
interface PortalBootstrap {
    user: UserProfile;
    branding: Branding;
    navigation: NavItem[];
    stats: { projects: number; tasks: number; tickets: number; messages: number; overdue_tasks: number; };
    projects: Project[];
    tasks: Task[];
    tickets: Ticket[];
    messages: MessageItem[];
    files: FileItem[];
    departments: Department[];
    tags: Tag[];
    urls: { portal: string; login: string; tickets: string; logout: string; };
}

const runtime = window.aosaiPortalData || {};
const navIcons: Record<string, any> = { dashboard: LayoutDashboard, projects: FolderKanban, tasks: ClipboardList, tickets: LifeBuoy, files: Files, messages: Mail, profile: UserCircle2 };

function tone(status: string) {
    switch (status) {
        case 'completed':
        case 'resolved':
        case 'closed':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        case 'in_progress':
        case 'waiting':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        default:
            return 'bg-slate-100 text-slate-700 border-slate-200';
    }
}

function priorityTone(priority: string) {
    switch (priority) {
        case 'urgent': return 'text-rose-600';
        case 'high': return 'text-orange-600';
        case 'medium': return 'text-sky-600';
        default: return 'text-slate-500';
    }
}

function StatCard({ label, value, help }: { label: string; value: number; help: string }) {
    return (
        <div className="rounded-[24px] border border-white/60 bg-white/90 p-5 shadow-[0_18px_45px_rgba(15,23,42,0.08)] backdrop-blur">
            <p className="text-xs uppercase tracking-[0.18em] text-slate-400">{label}</p>
            <p className="mt-3 text-3xl font-semibold text-slate-900">{value}</p>
            <p className="mt-2 text-sm text-slate-500">{help}</p>
        </div>
    );
}

function App() {
    const [data, setData] = useState<PortalBootstrap | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [activeView, setActiveView] = useState(runtime.initialView || 'dashboard');
    const [ticketSaving, setTicketSaving] = useState(false);
    const [profileSaving, setProfileSaving] = useState(false);
    const [ticketForm, setTicketForm] = useState({ subject: '', content: '', department_id: '', project_id: '', priority: 'medium', tags: '' });
    const [profileForm, setProfileForm] = useState({ first_name: '', last_name: '', email: '', password: '' });

    async function loadBootstrap() {
        setLoading(true);
        setError('');
        try {
            const response = await apiGet<PortalBootstrap>('/aosai/v1/portal/bootstrap');
            setData(response);
            setProfileForm({ first_name: response.user.first_name || '', last_name: response.user.last_name || '', email: response.user.email || '', password: '' });
            if (runtime.initialView) setActiveView(runtime.initialView);
        } catch (err: any) {
            setError(err.message || 'Failed to load the workspace.');
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => { loadBootstrap(); }, []);
    useEffect(() => {
        const enablePwa = runtime.branding?.enable_pwa;
        if (!enablePwa || !runtime.serviceWorkerUrl || !('serviceWorker' in navigator)) return;
        navigator.serviceWorker.register(runtime.serviceWorkerUrl).catch(() => undefined);
    }, []);

    const styles = useMemo(() => {
        const primary = data?.branding.primary_color || runtime.branding?.primary_color || '#0f766e';
        const secondary = data?.branding.secondary_color || runtime.branding?.secondary_color || '#f59e0b';
        return { background: `radial-gradient(circle at top left, ${primary}22, transparent 30%), radial-gradient(circle at top right, ${secondary}22, transparent 28%), linear-gradient(180deg, #f8fafc 0%, #ecfeff 100%)`, fontFamily: '"Trebuchet MS", "Segoe UI", sans-serif' } as React.CSSProperties;
    }, [data]);
    const brandLogo = data?.branding.company_logo_url || runtime.branding?.company_logo_url || getBrandLogo();
    const animatedLogo = getAnimatedBrandLogo(brandLogo);
    const footerLinks = [
        data?.branding.company_website ? { label: 'Website', href: data.branding.company_website } : null,
        data?.branding.privacy_policy_url ? { label: 'Privacy Policy', href: data.branding.privacy_policy_url } : null,
        data?.branding.terms_url ? { label: 'Terms', href: data.branding.terms_url } : null,
    ].filter(Boolean) as Array<{ label: string; href: string }>;
    const footerCredit = (data?.branding.footer_credit_text || '').trim();

    async function handleCreateTicket(e: React.FormEvent) {
        e.preventDefault();
        setTicketSaving(true);
        try {
            await apiPost('/aosai/v1/tickets', { subject: ticketForm.subject, content: ticketForm.content, department_id: ticketForm.department_id ? Number(ticketForm.department_id) : undefined, project_id: ticketForm.project_id ? Number(ticketForm.project_id) : undefined, priority: ticketForm.priority, tags: ticketForm.tags });
            setTicketForm({ subject: '', content: '', department_id: '', project_id: '', priority: 'medium', tags: '' });
            setActiveView('tickets');
            await loadBootstrap();
        } catch (err: any) {
            alert(err.message || 'Unable to create ticket.');
        } finally {
            setTicketSaving(false);
        }
    }

    async function handleTicketStatus(ticketId: number, status: string) {
        try {
            await apiPut(`/aosai/v1/tickets/${ticketId}`, { status });
            await loadBootstrap();
        } catch (err: any) {
            alert(err.message || 'Unable to update the ticket.');
        }
    }

    async function handleProfileSave(e: React.FormEvent) {
        e.preventDefault();
        setProfileSaving(true);
        try {
            await apiPost('/aosai/v1/profile/me', profileForm);
            await loadBootstrap();
            setProfileForm((prev) => ({ ...prev, password: '' }));
        } catch (err: any) {
            alert(err.message || 'Unable to update your profile.');
        } finally {
            setProfileSaving(false);
        }
    }

    if (loading) {
        return (
            <div className="aosai-app-container min-h-screen flex items-center justify-center" style={styles}>
                <div className="flex flex-col items-center gap-4 rounded-[32px] border border-white/70 bg-white/90 px-7 py-6 shadow-[0_24px_60px_rgba(15,23,42,0.12)] backdrop-blur">
                    {animatedLogo ? (
                        <img src={animatedLogo} alt="Agency OS AI" className="w-36 h-auto" />
                    ) : (
                        <Loader2 className="h-7 w-7 animate-spin text-slate-700" />
                    )}
                    <p className="text-sm font-medium text-slate-600">Loading workspace</p>
                </div>
            </div>
        );
    }

    if (error || !data) {
        return (
            <div className="aosai-app-container min-h-screen flex items-center justify-center p-6" style={styles}>
                <div className="max-w-lg rounded-[28px] border border-rose-200 bg-white p-8 text-center shadow-xl">
                    <p className="text-sm uppercase tracking-[0.2em] text-rose-400">Portal Error</p>
                    <h1 className="mt-3 text-3xl font-semibold text-slate-900">We couldn't open the workspace</h1>
                    <p className="mt-3 text-slate-600">{error || 'Please refresh the page and try again.'}</p>
                    <button onClick={loadBootstrap} className="mt-6 rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white">Try again</button>
                </div>
            </div>
        );
    }

    const isClient = data.user.portal_type === 'client';
    const canManageTickets = data.user.portal_type !== 'client';

    return (
        <div className="aosai-app-container min-h-screen" style={styles}>
            <div className="mx-auto flex min-h-screen max-w-[1500px] gap-6 p-4 md:p-6">
                <aside className="hidden lg:flex w-[290px] shrink-0 flex-col rounded-[32px] border border-white/70 bg-white/80 p-5 shadow-[0_22px_60px_rgba(15,23,42,0.1)] backdrop-blur">
                    <div className="rounded-[24px] p-5 text-white" style={{ background: `linear-gradient(135deg, ${data.branding.primary_color}, #0f172a)` }}>
                        <div className="flex items-center gap-3">
                            {brandLogo ? (
                                <img src={brandLogo} alt={data.branding.company_name || 'Agency OS AI'} className="h-12 w-12 rounded-[18px] bg-white/10 object-contain p-1.5" />
                            ) : null}
                            <div className="min-w-0">
                                <p className="text-xs uppercase tracking-[0.2em] text-white/70">{data.branding.company_name}</p>
                                <h1 className="mt-1 text-2xl font-semibold leading-tight">{data.branding.portal_name}</h1>
                            </div>
                        </div>
                        <p className="mt-3 text-sm text-white/80">{data.branding.welcome_text}</p>
                    </div>
                    <nav className="mt-6 space-y-2">
                        {data.navigation.map((item) => {
                            const Icon = navIcons[item.id] || Briefcase;
                            const active = activeView === item.id;
                            return (
                                <button key={item.id} onClick={() => setActiveView(item.id)} className={`flex w-full items-center gap-3 rounded-2xl px-4 py-3 text-left text-sm font-medium transition ${active ? 'bg-slate-900 text-white shadow-lg' : 'text-slate-600 hover:bg-slate-100'}`}>
                                    <Icon className="h-4 w-4" />
                                    {item.label}
                                    <ChevronRight className="ml-auto h-4 w-4 opacity-50" />
                                </button>
                            );
                        })}
                    </nav>
                </aside>

                <main className="min-w-0 flex-1">
                    <header className="rounded-[32px] border border-white/70 bg-white/80 p-5 shadow-[0_22px_60px_rgba(15,23,42,0.08)] backdrop-blur">
                        <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div className="min-w-0">
                                {brandLogo ? (
                                    <img src={brandLogo} alt={data.branding.company_name || 'Agency OS AI'} className="mb-3 h-11 w-11 rounded-[18px] object-contain lg:hidden" />
                                ) : null}
                                <p className="text-xs uppercase tracking-[0.18em] text-slate-400">{isClient ? 'Client Workspace' : 'Team Workspace'}</p>
                                <h2 className="mt-2 text-3xl font-semibold text-slate-900">Welcome, {data.user.first_name || data.user.display_name || data.user.name || 'there'}</h2>
                                <p className="mt-2 text-sm text-slate-500">Projects, tickets, files, and communication are all centralized here.</p>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="text-right">
                                    <p className="text-sm font-semibold text-slate-900">{data.user.display_name || data.user.name}</p>
                                    <p className="text-xs text-slate-500">{data.user.email}</p>
                                </div>
                                {data.user.avatar_url ? (
                                    <img src={data.user.avatar_url} alt={data.user.display_name || data.user.name} className="h-12 w-12 rounded-2xl object-cover" />
                                ) : (
                                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-white">{(data.user.display_name || data.user.name || 'U').charAt(0)}</div>
                                )}
                                <a href={data.urls.logout} className="rounded-full border border-slate-200 p-3 text-slate-500 hover:bg-slate-100"><LogOut className="h-4 w-4" /></a>
                            </div>
                        </div>
                    </header>

                    <div className="mt-6 lg:hidden flex gap-2 overflow-x-auto pb-1">
                        {data.navigation.map((item) => {
                            const active = activeView === item.id;
                            return <button key={item.id} onClick={() => setActiveView(item.id)} className={`rounded-full px-4 py-2 text-sm font-medium ${active ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border border-slate-200'}`}>{item.label}</button>;
                        })}
                    </div>

                    <section className="mt-6 space-y-6">
                        {activeView === 'dashboard' && (
                            <>
                                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                                    <StatCard label="Projects" value={data.stats.projects} help="Active workspaces you can access" />
                                    <StatCard label="Tasks" value={data.stats.tasks} help="Work items currently assigned" />
                                    <StatCard label="Tickets" value={data.stats.tickets} help="Open support conversations" />
                                    <StatCard label="Messages" value={data.stats.messages} help="Recent collaboration updates" />
                                    <StatCard label="Overdue" value={data.stats.overdue_tasks} help="Tasks that need attention" />
                                </div>
                                <div className="grid gap-6 xl:grid-cols-[1.3fr_.9fr]">
                                    <div className="rounded-[28px] border border-white/70 bg-white/90 p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)]">
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-xl font-semibold text-slate-900">Project pulse</h3>
                                            <button onClick={() => setActiveView('projects')} className="text-sm font-medium text-slate-500">See all</button>
                                        </div>
                                        <div className="mt-5 grid gap-4 md:grid-cols-2">
                                            {data.projects.slice(0, 4).map((project) => (
                                                <div key={project.id} className="rounded-[22px] border border-slate-200 bg-slate-50 p-5">
                                                    <div className="flex items-start justify-between gap-3">
                                                        <div>
                                                            <h4 className="font-semibold text-slate-900">{project.name}</h4>
                                                            <p className="mt-1 text-sm text-slate-500 line-clamp-2">{project.description || 'No project summary added yet.'}</p>
                                                        </div>
                                                        <span className={`rounded-full border px-3 py-1 text-xs ${tone(project.status || 'active')}`}>{project.status || 'active'}</span>
                                                    </div>
                                                    <div className="mt-4 h-2 overflow-hidden rounded-full bg-slate-200"><div className="h-full rounded-full" style={{ width: `${project.progress || 0}%`, background: `linear-gradient(90deg, ${data.branding.primary_color}, ${data.branding.secondary_color})` }} /></div>
                                                    <div className="mt-3 flex items-center justify-between text-sm text-slate-500">
                                                        <span>{project.progress || 0}% complete</span>
                                                        <span>{project.member_count || 0} members</span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                    <div className="rounded-[28px] border border-white/70 bg-white/90 p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)]">
                                        <div className="flex items-center justify-between">
                                            <h3 className="text-xl font-semibold text-slate-900">Support snapshot</h3>
                                            <button onClick={() => setActiveView('tickets')} className="text-sm font-medium text-slate-500">Manage</button>
                                        </div>
                                        <div className="mt-5 space-y-4">
                                            {data.tickets.slice(0, 4).map((ticket) => (
                                                <div key={ticket.id} className="rounded-[20px] border border-slate-200 bg-slate-50 p-4">
                                                    <div className="flex items-center justify-between gap-3">
                                                        <p className="font-medium text-slate-900">{ticket.subject}</p>
                                                        <span className={`rounded-full border px-3 py-1 text-xs ${tone(ticket.status)}`}>{ticket.status.replace('_', ' ')}</span>
                                                    </div>
                                                    <p className="mt-2 text-sm text-slate-500">{ticket.department_name || 'Support'} • {ticket.priority}</p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            </>
                        )}
                        {activeView === 'projects' && (
                            <div className="grid gap-5 xl:grid-cols-2">
                                {data.projects.map((project) => (
                                    <article key={project.id} className="rounded-[28px] border border-white/70 bg-white/90 p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)]">
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <p className="text-xs uppercase tracking-[0.18em] text-slate-400">Project</p>
                                                <h3 className="mt-2 text-2xl font-semibold text-slate-900">{project.name}</h3>
                                                <p className="mt-3 text-sm leading-7 text-slate-500">{project.description || 'No project summary available yet.'}</p>
                                            </div>
                                            <span className={`rounded-full border px-3 py-1 text-xs ${tone(project.status || 'active')}`}>{project.status || 'active'}</span>
                                        </div>
                                        <div className="mt-5 h-2 overflow-hidden rounded-full bg-slate-200"><div className="h-full rounded-full" style={{ width: `${project.progress || 0}%`, background: `linear-gradient(90deg, ${data.branding.primary_color}, ${data.branding.secondary_color})` }} /></div>
                                        <div className="mt-4 flex flex-wrap gap-2">{(project.tags || []).map((tag) => <span key={tag.id} className="rounded-full px-3 py-1 text-xs text-white" style={{ backgroundColor: tag.color }}>{tag.name}</span>)}</div>
                                        <div className="mt-5 flex items-center justify-between text-sm text-slate-500">
                                            <span>Owner: {project.owner_name || 'Team'}</span>
                                            <span>Due: {project.due_date || 'Open timeline'}</span>
                                        </div>
                                    </article>
                                ))}
                            </div>
                        )}

                        {activeView === 'tasks' && (
                            <div className="grid gap-4 xl:grid-cols-2">
                                {data.tasks.map((task) => (
                                    <div key={task.id} className="rounded-[26px] border border-white/70 bg-white/90 p-5 shadow-[0_18px_45px_rgba(15,23,42,0.08)]">
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <h3 className="text-lg font-semibold text-slate-900">{task.title}</h3>
                                                <p className="mt-1 text-sm text-slate-500">{task.project_name || 'Workspace'} • {task.task_list_name || 'Task list'}</p>
                                            </div>
                                            <span className={`rounded-full border px-3 py-1 text-xs ${tone(task.status)}`}>{task.status.replace('_', ' ')}</span>
                                        </div>
                                        <div className="mt-4 flex items-center justify-between text-sm">
                                            <span className={priorityTone(task.priority)}>{task.priority} priority</span>
                                            <span className="text-slate-500">Due: {task.due_date || 'No due date'}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {activeView === 'tickets' && (
                            <div className="grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
                                <div className="space-y-4">
                                    {data.tickets.map((ticket) => (
                                        <div key={ticket.id} className="rounded-[28px] border border-white/70 bg-white/90 p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)]">
                                            <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                                                <div>
                                                    <h3 className="text-xl font-semibold text-slate-900">{ticket.subject}</h3>
                                                    <p className="mt-2 text-sm leading-7 text-slate-500">{ticket.content}</p>
                                                    <div className="mt-3 flex flex-wrap gap-2 text-xs text-slate-500">
                                                        <span>{ticket.department_name || 'Support'}</span><span>•</span><span>{ticket.project_name || 'General request'}</span><span>•</span><span>{ticket.notes_count || 0} notes</span>
                                                    </div>
                                                </div>
                                                <div className="flex flex-col gap-2 md:items-end">
                                                    <span className={`rounded-full border px-3 py-1 text-xs ${tone(ticket.status)}`}>{ticket.status.replace('_', ' ')}</span>
                                                    {canManageTickets ? (
                                                        <select value={ticket.status} onChange={(e) => handleTicketStatus(ticket.id, e.target.value)} className="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-600">
                                                            <option value="open">Open</option>
                                                            <option value="in_progress">In Progress</option>
                                                            <option value="waiting">Waiting</option>
                                                            <option value="resolved">Resolved</option>
                                                            <option value="closed">Closed</option>
                                                        </select>
                                                    ) : null}
                                                </div>
                                            </div>
                                            <div className="mt-4 flex flex-wrap gap-2">{(ticket.tags || []).map((tag) => <span key={tag.id} className="rounded-full px-3 py-1 text-xs text-white" style={{ backgroundColor: tag.color }}>{tag.name}</span>)}</div>
                                        </div>
                                    ))}
                                </div>
                                <form onSubmit={handleCreateTicket} className="rounded-[28px] border border-white/70 bg-white/95 p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)]">
                                    <p className="text-xs uppercase tracking-[0.18em] text-slate-400">Create Ticket</p>
                                    <h3 className="mt-2 text-2xl font-semibold text-slate-900">Open a new request</h3>
                                    <div className="mt-5 space-y-4">
                                        <input value={ticketForm.subject} onChange={(e) => setTicketForm((prev) => ({ ...prev, subject: e.target.value }))} placeholder="Subject" className="w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                        <textarea value={ticketForm.content} onChange={(e) => setTicketForm((prev) => ({ ...prev, content: e.target.value }))} placeholder="Tell us what you need" rows={6} className="w-full rounded-2xl border border-slate-200 px-4 py-3" />
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <select value={ticketForm.department_id} onChange={(e) => setTicketForm((prev) => ({ ...prev, department_id: e.target.value }))} className="rounded-2xl border border-slate-200 px-4 py-3 text-slate-600">
                                                <option value="">Auto-assign department</option>
                                                {data.departments.map((department) => <option key={department.id} value={department.id}>{department.name}</option>)}
                                            </select>
                                            <select value={ticketForm.project_id} onChange={(e) => setTicketForm((prev) => ({ ...prev, project_id: e.target.value }))} className="rounded-2xl border border-slate-200 px-4 py-3 text-slate-600">
                                                <option value="">General request</option>
                                                {data.projects.map((project) => <option key={project.id} value={project.id}>{project.name}</option>)}
                                            </select>
                                        </div>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <select value={ticketForm.priority} onChange={(e) => setTicketForm((prev) => ({ ...prev, priority: e.target.value }))} className="rounded-2xl border border-slate-200 px-4 py-3 text-slate-600">
                                                <option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option>
                                            </select>
                                            <input value={ticketForm.tags} onChange={(e) => setTicketForm((prev) => ({ ...prev, tags: e.target.value }))} placeholder="Tags, comma separated" className="rounded-2xl border border-slate-200 px-4 py-3" />
                                        </div>
                                        <button disabled={ticketSaving} className="inline-flex w-full items-center justify-center gap-2 rounded-full px-5 py-3 text-sm font-semibold text-white" style={{ background: `linear-gradient(135deg, ${data.branding.primary_color}, ${data.branding.secondary_color})` }}>
                                            {ticketSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Send className="h-4 w-4" />} Submit Ticket
                                        </button>
                                    </div>
                                </form>
                            </div>
                        )}
                        {activeView === 'files' && (
                            <div className="grid gap-4 xl:grid-cols-2">
                                {data.files.map((file) => (
                                    <a key={file.id} href={file.url} target="_blank" rel="noreferrer" className="rounded-[26px] border border-white/70 bg-white/90 p-5 shadow-[0_18px_45px_rgba(15,23,42,0.08)] transition hover:-translate-y-0.5">
                                        <h3 className="text-lg font-semibold text-slate-900">{file.filename}</h3>
                                        <p className="mt-2 text-sm text-slate-500">{file.project_name || 'General file'}</p>
                                        <p className="mt-3 text-xs uppercase tracking-[0.18em] text-slate-400">Uploaded by {file.uploaded_by_name || 'Team'}</p>
                                    </a>
                                ))}
                            </div>
                        )}

                        {activeView === 'messages' && (
                            <div className="space-y-4">
                                {data.messages.map((message) => (
                                    <div key={message.id} className="rounded-[26px] border border-white/70 bg-white/90 p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)]">
                                        <div className="flex items-center justify-between gap-3">
                                            <div>
                                                <h3 className="text-lg font-semibold text-slate-900">{message.title || 'Project update'}</h3>
                                                <p className="mt-1 text-sm text-slate-500">{message.project_name || 'General'} • {message.author_name || 'Team'}</p>
                                            </div>
                                        </div>
                                        <p className="mt-3 text-sm leading-7 text-slate-600">{message.content}</p>
                                    </div>
                                ))}
                            </div>
                        )}

                        {activeView === 'profile' && (
                            <form onSubmit={handleProfileSave} className="rounded-[28px] border border-white/70 bg-white/95 p-6 shadow-[0_18px_45px_rgba(15,23,42,0.08)]">
                                <p className="text-xs uppercase tracking-[0.18em] text-slate-400">Profile</p>
                                <h3 className="mt-2 text-2xl font-semibold text-slate-900">Manage your access details</h3>
                                <div className="mt-5 grid gap-4 md:grid-cols-2">
                                    <input value={profileForm.first_name} onChange={(e) => setProfileForm((prev) => ({ ...prev, first_name: e.target.value }))} placeholder="First name" className="rounded-2xl border border-slate-200 px-4 py-3" />
                                    <input value={profileForm.last_name} onChange={(e) => setProfileForm((prev) => ({ ...prev, last_name: e.target.value }))} placeholder="Last name" className="rounded-2xl border border-slate-200 px-4 py-3" />
                                    <input value={profileForm.email} onChange={(e) => setProfileForm((prev) => ({ ...prev, email: e.target.value }))} placeholder="Email" className="rounded-2xl border border-slate-200 px-4 py-3 md:col-span-2" />
                                    <input type="password" value={profileForm.password} onChange={(e) => setProfileForm((prev) => ({ ...prev, password: e.target.value }))} placeholder="New password (optional)" className="rounded-2xl border border-slate-200 px-4 py-3 md:col-span-2" />
                                </div>
                                <button disabled={profileSaving} className="mt-5 inline-flex items-center gap-2 rounded-full bg-slate-900 px-5 py-3 text-sm font-semibold text-white">
                                    {profileSaving ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />} Save profile
                                </button>
                            </form>
                        )}
                    </section>

                    <footer className="mt-6 rounded-[28px] border border-white/70 bg-white/80 px-5 py-4 shadow-[0_18px_45px_rgba(15,23,42,0.08)] backdrop-blur">
                        <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div className="space-y-1">
                                <p className="text-sm font-medium text-slate-800">{data.branding.company_name || 'Agency OS AI'}</p>
                                <p className="text-xs text-slate-500">
                                    {data.branding.support_email ? (
                                        <a href={`mailto:${data.branding.support_email}`} className="hover:text-slate-700">
                                            {data.branding.support_email}
                                        </a>
                                    ) : 'Workspace support'}
                                </p>
                                {footerCredit ? <p className="text-xs text-slate-500">{footerCredit}</p> : null}
                                <p className="text-xs text-slate-500">
                                    A product of <a href="https://themefreex.com" target="_blank" rel="noreferrer" className="font-medium text-slate-700 hover:text-slate-900">Themefreex</a> by <a href="https://codefreex.com" target="_blank" rel="noreferrer" className="font-medium text-slate-700 hover:text-slate-900">Codefreex</a>.
                                </p>
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {footerLinks.map((link) => (
                                    <a key={link.href} href={link.href} target="_blank" rel="noreferrer" className="rounded-full border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-100">
                                        {link.label}
                                    </a>
                                ))}
                            </div>
                        </div>
                    </footer>
                </main>
            </div>
        </div>
    );
}

const rootElement = document.getElementById('aosai-portal-root');
if (rootElement) {
    createRoot(rootElement).render(<App />);
    requestAnimationFrame(() => {
        const preloader = document.getElementById('aosai-portal-preloader');
        if (!preloader) return;
        preloader.classList.add('is-ready');
        window.setTimeout(() => preloader.remove(), 260);
    });
}

