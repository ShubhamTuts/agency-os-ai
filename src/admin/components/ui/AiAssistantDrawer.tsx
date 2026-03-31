import React, { useEffect, useState } from 'react';
import { Bot, RefreshCw, Send, Sparkles, X } from 'lucide-react';
import { apiPost } from '../../services/api';

interface AiAssistantDrawerProps {
    open: boolean;
    onClose: () => void;
}

interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
}

const QUICK_PROMPTS = [
    'Summarize the highest-priority work I should focus on today.',
    'Turn a messy project update into a clear client-ready progress note.',
    'Suggest the next 5 actions for a delayed project.',
    'Help me triage blockers across my active tasks and tickets.',
] as const;

export function AiAssistantDrawer({ open, onClose }: AiAssistantDrawerProps) {
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        if (!open) return;
        function handleEscape(event: KeyboardEvent) {
            if (event.key === 'Escape') onClose();
        }
        window.addEventListener('keydown', handleEscape);
        return () => window.removeEventListener('keydown', handleEscape);
    }, [open, onClose]);

    async function sendMessage(message: string) {
        const trimmed = message.trim();
        if (!trimmed || loading) return;
        setMessages((prev) => [...prev, { role: 'user', content: trimmed }]);
        setInput('');
        setLoading(true);
        try {
            const response = await apiPost<{ reply?: string; message?: string }>('/aosai/v1/ai/chat', { message: trimmed });
            setMessages((prev) => [...prev, { role: 'assistant', content: response?.reply || response?.message || 'No response received.' }]);
        } catch (error: any) {
            setMessages((prev) => [...prev, { role: 'assistant', content: error?.message || 'The AI assistant could not respond right now.' }]);
        } finally {
            setLoading(false);
        }
    }

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex justify-end bg-slate-950/35 backdrop-blur-sm">
            <button type="button" className="flex-1 cursor-default" aria-label="Close AI assistant overlay" onClick={onClose} />
            <aside className="relative flex h-full w-full max-w-xl flex-col border-l border-slate-200 bg-white shadow-[0_24px_60px_rgba(15,23,42,0.22)]">
                <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                    <div className="flex items-center gap-3">
                        <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-100 text-amber-700">
                            <Sparkles className="h-5 w-5" />
                        </div>
                        <div>
                            <h2 className="text-base font-semibold text-slate-900">Workspace AI Assistant</h2>
                            <p className="text-xs text-slate-500">Available across the dashboard for planning, triage, and drafting.</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <button
                            type="button"
                            onClick={() => setMessages([])}
                            className="inline-flex items-center gap-1 rounded-full border border-slate-200 px-3 py-2 text-xs font-medium text-slate-600 hover:bg-slate-50"
                        >
                            <RefreshCw className="h-3.5 w-3.5" /> Clear
                        </button>
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-full border border-slate-200 p-2 text-slate-600 hover:bg-slate-50"
                            aria-label="Close AI assistant"
                        >
                            <X className="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div className="flex-1 overflow-y-auto px-5 py-5">
                    {messages.length === 0 ? (
                        <div className="space-y-5">
                            <div className="rounded-[28px] border border-slate-200 bg-gradient-to-br from-amber-50 via-white to-teal-50 p-6">
                                <div className="flex items-center gap-3">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-900 text-white">
                                        <Bot className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p className="text-sm font-semibold text-slate-900">Fast operational help</p>
                                        <p className="text-sm text-slate-500">Use the assistant for planning, summaries, delivery coaching, and AI drafting without leaving the page you are on.</p>
                                    </div>
                                </div>
                            </div>
                            <div className="grid gap-3">
                                {QUICK_PROMPTS.map((prompt) => (
                                    <button
                                        key={prompt}
                                        type="button"
                                        onClick={() => void sendMessage(prompt)}
                                        className="rounded-2xl border border-slate-200 px-4 py-4 text-left text-sm text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                                    >
                                        {prompt}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {messages.map((message, index) => (
                                <div key={`${message.role}-${index}`} className={`flex ${message.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                                    <div className={`max-w-[88%] rounded-[22px] px-4 py-3 text-sm leading-6 ${message.role === 'user' ? 'bg-slate-900 text-white' : 'border border-slate-200 bg-slate-50 text-slate-700'}`}>
                                        {message.content}
                                    </div>
                                </div>
                            ))}
                            {loading && (
                                <div className="flex justify-start">
                                    <div className="rounded-[22px] border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                                        Thinking through the workspace context...
                                    </div>
                                </div>
                            )}
                        </div>
                    )}
                </div>

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        void sendMessage(input);
                    }}
                    className="border-t border-slate-200 px-5 py-4"
                >
                    <div className="flex gap-3">
                        <textarea
                            value={input}
                            onChange={(event) => setInput(event.target.value)}
                            placeholder="Ask for a summary, a plan, a client draft, or next actions..."
                            rows={3}
                            className="min-h-[96px] flex-1 rounded-3xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-slate-900"
                        />
                        <button
                            type="submit"
                            disabled={loading || !input.trim()}
                            className="inline-flex h-fit items-center gap-2 rounded-full bg-slate-900 px-4 py-3 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Send className="h-4 w-4" /> Send
                        </button>
                    </div>
                </form>
            </aside>
        </div>
    );
}
