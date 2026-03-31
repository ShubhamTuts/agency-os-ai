import React, { useEffect, useState } from 'react';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPost } from '../services/api';
import { Bot, Building2, CheckCircle, Key, Link2, Mail, PanelsTopLeft, Server, ShieldCheck, Sparkles, Wand2 } from 'lucide-react';

interface ShortcodeRef {
    label: string;
    shortcode: string;
    description: string;
}

interface SettingsState {
    openai_api_key: string;
    default_model: string;
    email_notifications: boolean;
    timezone: string;
    date_format: string;
    primary_color: string;
    company_name: string;
    company_email: string;
    company_phone: string;
    company_website: string;
    privacy_policy_url: string;
    terms_url: string;
    company_address: string;
    company_logo_url: string;
    support_email: string;
    email_from_name: string;
    email_from_email: string;
    email_footer_text: string;
    smtp_enabled: boolean;
    smtp_host: string;
    smtp_port: number;
    smtp_username: string;
    smtp_password: string;
    smtp_encryption: string;
    smtp_auth: boolean;
    inbound_ai_routing: boolean;
    inbound_email_token: string;
    portal_name: string;
    portal_welcome_title: string;
    portal_welcome_text: string;
    portal_secondary_color: string;
    portal_page_id: number;
    portal_login_page_id: number;
    portal_ticket_page_id: number;
    hide_admin_bar: boolean;
    force_frontend_dashboard: boolean;
    enable_pwa: boolean;
    show_footer_credit: boolean;
    footer_credit_text: string;
    ticket_ai_routing: boolean;
    ticket_default_priority: string;
    portal_dashboard_layout: string;
    portal_page_url?: string;
    portal_login_page_url?: string;
    portal_ticket_page_url?: string;
    inbound_email_endpoint?: string;
    inbound_email_pipe_endpoint?: string;
    shortcodes: ShortcodeRef[];
}

const MODELS = ['gpt-4o-mini', 'gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo'];
const DEFAULTS: SettingsState = {
    openai_api_key: '', default_model: 'gpt-4o-mini', email_notifications: true, timezone: 'UTC', date_format: 'F j, Y', primary_color: '#0f766e',
    company_name: '', company_email: '', company_phone: '', company_website: '', privacy_policy_url: '', terms_url: '', company_address: '', company_logo_url: '',
    support_email: '', email_from_name: '', email_from_email: '', email_footer_text: '',
    smtp_enabled: false, smtp_host: '', smtp_port: 587, smtp_username: '', smtp_password: '', smtp_encryption: 'tls', smtp_auth: true, inbound_ai_routing: true, inbound_email_token: '',
    portal_name: '', portal_welcome_title: '', portal_welcome_text: '', portal_secondary_color: '#f59e0b',
    portal_page_id: 0, portal_login_page_id: 0, portal_ticket_page_id: 0,
    hide_admin_bar: true, force_frontend_dashboard: true, enable_pwa: true, show_footer_credit: false, footer_credit_text: '', ticket_ai_routing: true,
    ticket_default_priority: 'medium', portal_dashboard_layout: 'split', shortcodes: [],
};

export default function Settings() {
    const [settings, setSettings] = useState<SettingsState>(DEFAULTS);
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [saved, setSaved] = useState(false);
    const [testing, setTesting] = useState(false);
    const [smtpTesting, setSmtpTesting] = useState(false);
    const [creatingPages, setCreatingPages] = useState(false);
    const [testResult, setTestResult] = useState<{ success: boolean; message: string } | null>(null);
    const [smtpResult, setSmtpResult] = useState<{ success: boolean; message: string } | null>(null);
    const [activeTab, setActiveTab] = useState<'company' | 'portal' | 'ai' | 'email'>('company');

    useEffect(() => {
        apiGet<SettingsState>('/aosai/v1/settings')
            .then((response) => setSettings((prev) => ({ ...prev, ...response })))
            .finally(() => setLoading(false));
    }, []);

    async function handleSave(e: React.FormEvent) {
        e.preventDefault();
        setSaving(true);
        setSaved(false);
        try {
            const result = await apiPost<SettingsState>('/aosai/v1/settings', settings);
            setSettings((prev) => ({ ...prev, ...result }));
            setSaved(true);
            setTimeout(() => setSaved(false), 2500);
        } catch (err: any) {
            alert(err.message || 'Unable to save settings.');
        } finally {
            setSaving(false);
        }
    }

    async function handleTestAI() {
        setTesting(true);
        setTestResult(null);
        try {
            const result = await apiPost<{ success: boolean; message: string }>('/aosai/v1/settings/test-ai', {
                provider: 'openai',
                api_key: settings.openai_api_key,
                model: settings.default_model,
            });
            setTestResult(result);
        } catch (err: any) {
            setTestResult({ success: false, message: err.message || 'Connection failed.' });
        } finally {
            setTesting(false);
        }
    }

    async function handleCreatePages() {
        setCreatingPages(true);
        try {
            const result = await apiPost<{ settings: SettingsState }>('/aosai/v1/settings/create-pages');
            if (result?.settings) setSettings((prev) => ({ ...prev, ...result.settings }));
        } catch (err: any) {
            alert(err.message || 'Unable to create portal pages.');
        } finally {
            setCreatingPages(false);
        }
    }

    async function handleTestSmtp() {
        setSmtpTesting(true);
        setSmtpResult(null);
        try {
            await apiPost<SettingsState>('/aosai/v1/settings', settings);
            const result = await apiPost<{ success: boolean; message: string }>('/aosai/v1/smtp/test');
            setSmtpResult(result);
        } catch (err: any) {
            setSmtpResult({ success: false, message: err.message || 'SMTP test failed.' });
        } finally {
            setSmtpTesting(false);
        }
    }

    if (loading) return <DashboardLayout><LoadingSpinner /></DashboardLayout>;

    const tabs = [
        { id: 'company', label: 'Company', icon: Building2 },
        { id: 'portal', label: 'Portal', icon: PanelsTopLeft },
        { id: 'ai', label: 'AI', icon: Bot },
        { id: 'email', label: 'Email', icon: Mail },
    ] as const;

    return (
        <DashboardLayout>
            <div className="mx-auto max-w-5xl space-y-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Workspace Settings</h1>
                        <p className="mt-1 text-sm text-gray-500">Configure branding, frontend portal access, AI routing, and client-facing experience.</p>
                    </div>
                    <div className="flex items-center gap-3">
                        <button type="button" onClick={handleCreatePages} disabled={creatingPages} className="inline-flex items-center gap-2 rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-60">
                            <Wand2 className="h-4 w-4" /> {creatingPages ? 'Creating...' : 'One-Click Page Creation'}
                        </button>
                        {saved && <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-3 py-2 text-sm font-medium text-emerald-700"><CheckCircle className="h-4 w-4" /> Saved</span>}
                    </div>
                </div>

                <div className="flex flex-wrap gap-2 rounded-2xl bg-gray-100 p-2 dark:bg-gray-800">
                    {tabs.map((tab) => (
                        <button key={tab.id} onClick={() => setActiveTab(tab.id)} className={`inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-medium transition ${activeTab === tab.id ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'}`}>
                            <tab.icon className="h-4 w-4" /> {tab.label}
                        </button>
                    ))}
                </div>

                <form onSubmit={handleSave} className="space-y-6">
                    {activeTab === 'company' && (
                        <div className="grid gap-6 lg:grid-cols-[1.1fr_.9fr]">
                            <section className="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <h2 className="text-xl font-semibold text-gray-900 dark:text-white">Company Identity</h2>
                                <div className="mt-5 grid gap-4 md:grid-cols-2">
                                    <input value={settings.company_name} onChange={(e) => setSettings((prev) => ({ ...prev, company_name: e.target.value }))} placeholder="Company name" className="rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <input value={settings.company_email} onChange={(e) => setSettings((prev) => ({ ...prev, company_email: e.target.value }))} placeholder="Company email" className="rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <input value={settings.company_phone} onChange={(e) => setSettings((prev) => ({ ...prev, company_phone: e.target.value }))} placeholder="Phone" className="rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <input value={settings.company_website} onChange={(e) => setSettings((prev) => ({ ...prev, company_website: e.target.value }))} placeholder="Website URL" className="rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <input value={settings.privacy_policy_url} onChange={(e) => setSettings((prev) => ({ ...prev, privacy_policy_url: e.target.value }))} placeholder="Privacy policy URL" className="rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <input value={settings.terms_url} onChange={(e) => setSettings((prev) => ({ ...prev, terms_url: e.target.value }))} placeholder="Terms URL" className="rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <input value={settings.company_logo_url} onChange={(e) => setSettings((prev) => ({ ...prev, company_logo_url: e.target.value }))} placeholder="Logo URL" className="rounded-2xl border border-gray-300 px-4 py-3 text-sm md:col-span-2" />
                                    <textarea value={settings.company_address} onChange={(e) => setSettings((prev) => ({ ...prev, company_address: e.target.value }))} placeholder="Company address" rows={4} className="rounded-2xl border border-gray-300 px-4 py-3 text-sm md:col-span-2" />
                                </div>
                            </section>
                            <section className="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <h2 className="text-xl font-semibold text-gray-900 dark:text-white">Brand Preview</h2>
                                <div className="mt-5 rounded-[28px] border border-gray-200 bg-gradient-to-br from-amber-50 via-white to-teal-50 p-6">
                                    <p className="text-xs uppercase tracking-[0.18em] text-gray-400">Preview</p>
                                    <h3 className="mt-3 text-2xl font-semibold text-gray-900">{settings.portal_name || settings.company_name || 'Your Workspace'}</h3>
                                    <p className="mt-2 text-sm text-gray-500">{settings.portal_welcome_text || 'Your branded portal message will appear here.'}</p>
                                    <div className="mt-6 flex gap-3">
                                        <div className="h-10 w-10 rounded-2xl border" style={{ backgroundColor: settings.primary_color }} />
                                        <div className="h-10 w-10 rounded-2xl border" style={{ backgroundColor: settings.portal_secondary_color }} />
                                    </div>
                                </div>
                            </section>
                        </div>
                    )}

                    {activeTab === 'portal' && (
                        <div className="grid gap-6 lg:grid-cols-[1.05fr_.95fr]">
                            <section className="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <h2 className="text-xl font-semibold text-gray-900 dark:text-white">Frontend Portal</h2>
                                <div className="mt-5 space-y-4">
                                    <input value={settings.portal_name} onChange={(e) => setSettings((prev) => ({ ...prev, portal_name: e.target.value }))} placeholder="Portal name" className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <input value={settings.portal_welcome_title} onChange={(e) => setSettings((prev) => ({ ...prev, portal_welcome_title: e.target.value }))} placeholder="Welcome title" className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <textarea value={settings.portal_welcome_text} onChange={(e) => setSettings((prev) => ({ ...prev, portal_welcome_text: e.target.value }))} placeholder="Welcome text" rows={4} className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <label className="text-sm font-medium text-gray-600">Primary Color<input type="color" value={settings.primary_color} onChange={(e) => setSettings((prev) => ({ ...prev, primary_color: e.target.value }))} className="mt-2 h-12 w-full rounded-xl border border-gray-300 p-1" /></label>
                                        <label className="text-sm font-medium text-gray-600">Accent Color<input type="color" value={settings.portal_secondary_color} onChange={(e) => setSettings((prev) => ({ ...prev, portal_secondary_color: e.target.value }))} className="mt-2 h-12 w-full rounded-xl border border-gray-300 p-1" /></label>
                                    </div>
                                    <div className="grid gap-3 md:grid-cols-2">
                                        <label className="flex items-center justify-between rounded-2xl border border-gray-200 px-4 py-3 text-sm"><span>Hide admin bar for client/employee users</span><input type="checkbox" checked={settings.hide_admin_bar} onChange={(e) => setSettings((prev) => ({ ...prev, hide_admin_bar: e.target.checked }))} /></label>
                                        <label className="flex items-center justify-between rounded-2xl border border-gray-200 px-4 py-3 text-sm"><span>Force client/employee users to frontend portal</span><input type="checkbox" checked={settings.force_frontend_dashboard} onChange={(e) => setSettings((prev) => ({ ...prev, force_frontend_dashboard: e.target.checked }))} /></label>
                                        <label className="flex items-center justify-between rounded-2xl border border-gray-200 px-4 py-3 text-sm"><span>Enable PWA install prompt</span><input type="checkbox" checked={settings.enable_pwa} onChange={(e) => setSettings((prev) => ({ ...prev, enable_pwa: e.target.checked }))} /></label>
                                        <label className="flex items-center justify-between rounded-2xl border border-gray-200 px-4 py-3 text-sm"><span>Show footer credit</span><input type="checkbox" checked={settings.show_footer_credit} onChange={(e) => setSettings((prev) => ({ ...prev, show_footer_credit: e.target.checked }))} /></label>
                                    </div>
                                    <input value={settings.footer_credit_text} onChange={(e) => setSettings((prev) => ({ ...prev, footer_credit_text: e.target.value }))} placeholder="Custom footer text (optional)" className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                </div>
                            </section>
                            <section className="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <div className="flex items-center justify-between">
                                    <h2 className="text-xl font-semibold text-gray-900 dark:text-white">Pages & Shortcodes</h2>
                                    <button type="button" onClick={handleCreatePages} disabled={creatingPages} className="inline-flex items-center gap-2 rounded-full bg-gray-900 px-4 py-2 text-sm font-medium text-white"><Sparkles className="h-4 w-4" /> {creatingPages ? 'Working...' : 'Create Pages'}</button>
                                </div>
                                <div className="mt-5 space-y-3 text-sm text-gray-600">
                                    <p><strong>Portal Page:</strong> {settings.portal_page_url || 'Not created yet'}</p>
                                    <p><strong>Login Page:</strong> {settings.portal_login_page_url || 'Not created yet'}</p>
                                    <p><strong>Support Page:</strong> {settings.portal_ticket_page_url || 'Not created yet'}</p>
                                </div>
                                <div className="mt-6 space-y-3">
                                    {settings.shortcodes.map((item) => (
                                        <div key={item.shortcode} className="rounded-2xl border border-gray-200 p-4">
                                            <div className="flex items-center justify-between gap-3">
                                                <div>
                                                    <p className="font-medium text-gray-900">{item.label}</p>
                                                    <p className="text-xs text-gray-500">{item.description}</p>
                                                </div>
                                                <Link2 className="h-4 w-4 text-gray-400" />
                                            </div>
                                            <input readOnly value={item.shortcode} className="mt-3 w-full rounded-xl border border-gray-300 bg-gray-50 px-3 py-2 text-sm" />
                                        </div>
                                    ))}
                                </div>
                            </section>
                        </div>
                    )}

                    {activeTab === 'ai' && (
                        <div className="grid gap-6 lg:grid-cols-[1fr_.9fr]">
                            <section className="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <h2 className="text-xl font-semibold text-gray-900 dark:text-white">AI Workspace</h2>
                                <div className="mt-5 space-y-4">
                                    <label className="block text-sm font-medium text-gray-600"><span className="inline-flex items-center gap-2"><Key className="h-4 w-4" /> OpenAI API Key</span><input type="password" value={settings.openai_api_key} onChange={(e) => setSettings((prev) => ({ ...prev, openai_api_key: e.target.value }))} className="mt-2 w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm" placeholder="sk-..." /></label>
                                    <label className="block text-sm font-medium text-gray-600">Default model<select value={settings.default_model} onChange={(e) => setSettings((prev) => ({ ...prev, default_model: e.target.value }))} className="mt-2 w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm">{MODELS.map((model) => <option key={model} value={model}>{model}</option>)}</select></label>
                                    <label className="flex items-center justify-between rounded-2xl border border-gray-200 px-4 py-3 text-sm"><span>Use AI for automatic department routing</span><input type="checkbox" checked={settings.ticket_ai_routing} onChange={(e) => setSettings((prev) => ({ ...prev, ticket_ai_routing: e.target.checked }))} /></label>
                                    <label className="block text-sm font-medium text-gray-600">Default ticket priority<select value={settings.ticket_default_priority} onChange={(e) => setSettings((prev) => ({ ...prev, ticket_default_priority: e.target.value }))} className="mt-2 w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm"><option value="low">Low</option><option value="medium">Medium</option><option value="high">High</option><option value="urgent">Urgent</option></select></label>
                                    <button type="button" onClick={handleTestAI} disabled={testing || !settings.openai_api_key} className="inline-flex items-center gap-2 rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 disabled:opacity-50"><Bot className="h-4 w-4" /> {testing ? 'Testing...' : 'Test AI Connection'}</button>
                                    {testResult && <div className={`rounded-2xl px-4 py-3 text-sm font-medium ${testResult.success ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>{testResult.message}</div>}
                                </div>
                            </section>
                            <section className="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <h2 className="text-xl font-semibold text-gray-900 dark:text-white">Regional Preferences</h2>
                                <div className="mt-5 space-y-4">
                                    <input value={settings.timezone} onChange={(e) => setSettings((prev) => ({ ...prev, timezone: e.target.value }))} placeholder="Timezone e.g. Asia/Kolkata" className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <input value={settings.date_format} onChange={(e) => setSettings((prev) => ({ ...prev, date_format: e.target.value }))} placeholder="Date format e.g. F j, Y" className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <select value={settings.portal_dashboard_layout} onChange={(e) => setSettings((prev) => ({ ...prev, portal_dashboard_layout: e.target.value }))} className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm"><option value="split">Split</option><option value="compact">Compact</option><option value="stacked">Stacked</option></select>
                                </div>
                            </section>
                        </div>
                    )}

                    {activeTab === 'email' && (
                        <div className="grid gap-6 lg:grid-cols-[1fr_.9fr]">
                            <section className="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                <h2 className="text-xl font-semibold text-gray-900 dark:text-white">Email Delivery</h2>
                                <div className="mt-5 space-y-4">
                                    <label className="flex items-center justify-between rounded-2xl border border-gray-200 px-4 py-3 text-sm"><span>Enable activity emails</span><input type="checkbox" checked={settings.email_notifications} onChange={(e) => setSettings((prev) => ({ ...prev, email_notifications: e.target.checked }))} /></label>
                                    <input value={settings.support_email} onChange={(e) => setSettings((prev) => ({ ...prev, support_email: e.target.value }))} placeholder="Support inbox email" className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <input value={settings.email_from_name} onChange={(e) => setSettings((prev) => ({ ...prev, email_from_name: e.target.value }))} placeholder="From name" className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <input value={settings.email_from_email} onChange={(e) => setSettings((prev) => ({ ...prev, email_from_email: e.target.value }))} placeholder="From email" className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                    <textarea value={settings.email_footer_text} onChange={(e) => setSettings((prev) => ({ ...prev, email_footer_text: e.target.value }))} placeholder="Email footer text" rows={5} className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                </div>

                                <div className="mt-8 border-t border-gray-200 pt-6">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <h3 className="text-lg font-semibold text-gray-900 dark:text-white">SMTP Transport</h3>
                                            <p className="mt-1 text-sm text-gray-500">Route outgoing mail through your own SMTP server instead of the host default mailer.</p>
                                        </div>
                                        <Server className="h-5 w-5 text-gray-400" />
                                    </div>
                                    <div className="mt-5 space-y-4">
                                        <div className="grid gap-3 md:grid-cols-2">
                                            <label className="flex items-center justify-between rounded-2xl border border-gray-200 px-4 py-3 text-sm"><span>Enable SMTP</span><input type="checkbox" checked={settings.smtp_enabled} onChange={(e) => setSettings((prev) => ({ ...prev, smtp_enabled: e.target.checked }))} /></label>
                                            <label className="flex items-center justify-between rounded-2xl border border-gray-200 px-4 py-3 text-sm"><span>Use SMTP authentication</span><input type="checkbox" checked={settings.smtp_auth} onChange={(e) => setSettings((prev) => ({ ...prev, smtp_auth: e.target.checked }))} /></label>
                                        </div>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <input value={settings.smtp_host} onChange={(e) => setSettings((prev) => ({ ...prev, smtp_host: e.target.value }))} placeholder="SMTP host" className="rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                            <input type="number" min={1} value={settings.smtp_port} onChange={(e) => setSettings((prev) => ({ ...prev, smtp_port: parseInt(e.target.value, 10) || 587 }))} placeholder="SMTP port" className="rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                            <input value={settings.smtp_username} onChange={(e) => setSettings((prev) => ({ ...prev, smtp_username: e.target.value }))} placeholder="SMTP username" className="rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                            <input type="password" value={settings.smtp_password} onChange={(e) => setSettings((prev) => ({ ...prev, smtp_password: e.target.value }))} placeholder="SMTP password" className="rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                            <select value={settings.smtp_encryption} onChange={(e) => setSettings((prev) => ({ ...prev, smtp_encryption: e.target.value }))} className="rounded-2xl border border-gray-300 px-4 py-3 text-sm md:col-span-2">
                                                <option value="tls">TLS</option>
                                                <option value="ssl">SSL</option>
                                                <option value="none">None</option>
                                            </select>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-3">
                                            <button type="button" onClick={handleTestSmtp} disabled={smtpTesting || !settings.smtp_enabled || !settings.smtp_host} className="inline-flex items-center gap-2 rounded-full border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 disabled:opacity-50">
                                                <Mail className="h-4 w-4" /> {smtpTesting ? 'Testing SMTP...' : 'Send SMTP Test'}
                                            </button>
                                            {smtpResult && <div className={`rounded-2xl px-4 py-3 text-sm font-medium ${smtpResult.success ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>{smtpResult.message}</div>}
                                        </div>
                                    </div>
                                </div>
                            </section>
                            <div className="space-y-6">
                                <section className="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                    <div className="flex items-center justify-between gap-3">
                                        <div>
                                            <h2 className="text-xl font-semibold text-gray-900 dark:text-white">Inbound Email Tickets</h2>
                                            <p className="mt-1 text-sm text-gray-500">Create tickets from email pipes, relay hooks, or automation tools with a signed inbound endpoint.</p>
                                        </div>
                                        <ShieldCheck className="h-5 w-5 text-gray-400" />
                                    </div>
                                    <div className="mt-5 space-y-4">
                                        <label className="flex items-center justify-between rounded-2xl border border-gray-200 px-4 py-3 text-sm"><span>Use AI routing on inbound email</span><input type="checkbox" checked={settings.inbound_ai_routing} onChange={(e) => setSettings((prev) => ({ ...prev, inbound_ai_routing: e.target.checked }))} /></label>
                                        <input value={settings.inbound_email_token} onChange={(e) => setSettings((prev) => ({ ...prev, inbound_email_token: e.target.value }))} placeholder="Inbound security token" className="w-full rounded-2xl border border-gray-300 px-4 py-3 text-sm" />
                                        <div>
                                            <p className="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Inbound endpoint</p>
                                            <input readOnly value={settings.inbound_email_endpoint || ''} className="w-full rounded-2xl border border-gray-300 bg-gray-50 px-4 py-3 text-sm" />
                                        </div>
                                        <div>
                                            <p className="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">Email pipe endpoint</p>
                                            <input readOnly value={settings.inbound_email_pipe_endpoint || ''} className="w-full rounded-2xl border border-gray-300 bg-gray-50 px-4 py-3 text-sm" />
                                        </div>
                                        <p className="text-xs leading-6 text-gray-500">Pass the token as `token` or `X-AOSAI-Inbound-Token`. Payloads can include `from_email`, `from_name`, `subject`, `body_plain`, `body_html`, and optional `priority`.</p>
                                    </div>
                                </section>

                                <section className="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                    <h2 className="text-xl font-semibold text-gray-900 dark:text-white">What This Powers</h2>
                                    <ul className="mt-5 space-y-3 text-sm text-gray-600">
                                        <li className="rounded-2xl border border-gray-200 px-4 py-3">Ticket acknowledgements to clients</li>
                                        <li className="rounded-2xl border border-gray-200 px-4 py-3">Department alerts for new support requests</li>
                                        <li className="rounded-2xl border border-gray-200 px-4 py-3">Task and collaboration notifications</li>
                                        <li className="rounded-2xl border border-gray-200 px-4 py-3">SMTP delivery and inbound email-to-ticket workflows</li>
                                    </ul>
                                </section>
                            </div>
                        </div>
                    )}

                    <div className="flex justify-end">
                        <button disabled={saving} className="inline-flex items-center gap-2 rounded-full bg-gray-900 px-5 py-3 text-sm font-semibold text-white">
                            <Mail className="h-4 w-4" /> {saving ? 'Saving...' : 'Save Settings'}
                        </button>
                    </div>
                </form>
            </div>
        </DashboardLayout>
    );
}

