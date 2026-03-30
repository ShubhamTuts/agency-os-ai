import React, { useState } from 'react';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { apiPost } from '../services/api';
import { Send, Sparkles, RefreshCw, Copy, CheckCheck, MessageSquare } from 'lucide-react';

interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
}

const PROMPTS = [
    { label: 'Generate task ideas', prompt: 'Generate 5 creative task ideas for a digital marketing project' },
    { label: 'Write project brief', prompt: 'Write a professional project brief for a new client engagement' },
    { label: 'Summarize tasks', prompt: 'Summarize the key points from the following task updates' },
    { label: 'Estimate timeline', prompt: 'Estimate a realistic timeline for completing 10 tasks with 2 developers' },
];

export default function AiPlayground() {
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [copied, setCopied] = useState(false);

    async function handleSend(e?: React.FormEvent) {
        e?.preventDefault();
        if (!input.trim() || loading) return;
        const userMsg = input.trim();
        setInput('');
        setMessages(prev => [...prev, { role: 'user', content: userMsg }]);
        setLoading(true);
        try {
            const res = await apiPost<{ reply?: string; message?: string }>('/aosai/v1/ai/chat', { message: userMsg });
            setMessages(prev => [...prev, { role: 'assistant', content: res?.reply || res?.message || 'No response received.' }]);
        } catch (err: any) {
            setMessages(prev => [...prev, { role: 'assistant', content: `Error: ${err.message}` }]);
        } finally { setLoading(false); }
    }

    async function handlePrompt(prompt: string) {
        setInput(prompt);
    }

    function handleCopy(text: string) {
        navigator.clipboard.writeText(text);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    }

    function clearChat() {
        setMessages([]);
    }

    return (
        <DashboardLayout>
            <div className="max-w-4xl mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <Sparkles className="w-6 h-6 text-purple-500" /> AI Playground
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">Chat with AI to generate content, ideas, and more</p>
                    </div>
                    {messages.length > 0 && (
                        <button onClick={clearChat} className="text-sm text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 flex items-center gap-1">
                            <RefreshCw className="w-4 h-4" /> Clear
                        </button>
                    )}
                </div>

                <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col" style={{ height: 'calc(100vh - 220px)' }}>
                    <div className="flex-1 overflow-y-auto p-4 space-y-4">
                        {messages.length === 0 ? (
                            <div className="h-full flex flex-col items-center justify-center text-center space-y-6">
                                <div className="w-16 h-16 rounded-full bg-purple-100 dark:bg-purple-900/20 flex items-center justify-center">
                                    <Sparkles className="w-8 h-8 text-purple-500" />
                                </div>
                                <div>
                                    <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-2">How can I help you today?</h2>
                                    <p className="text-sm text-gray-500 max-w-md">Try one of these prompts or ask me anything about your projects</p>
                                </div>
                                <div className="grid grid-cols-2 gap-3 w-full max-w-lg">
                                    {PROMPTS.map((p) => (
                                        <button key={p.label} onClick={() => handlePrompt(p.prompt)}
                                            className="text-left px-4 py-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                                            <p className="text-sm font-medium text-gray-700 dark:text-gray-300">{p.label}</p>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <>
                                {messages.map((msg, i) => (
                                    <div key={i} className={`flex gap-3 ${msg.role === 'user' ? 'flex-row-reverse' : ''}`}>
                                        <div className={`w-8 h-8 rounded-full flex-shrink-0 flex items-center justify-center text-sm font-medium ${msg.role === 'user' ? 'bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400' : 'bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400'}`}>
                                            {msg.role === 'user' ? 'Y' : <Sparkles className="w-4 h-4" />}
                                        </div>
                                        <div className={`flex-1 max-w-[80%] ${msg.role === 'user' ? 'text-right' : ''}`}>
                                            <div className={`inline-block text-sm p-3 rounded-xl ${msg.role === 'user' ? 'bg-primary-600 text-white rounded-tr-none' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-white rounded-tl-none'}`}>
                                                <p className="whitespace-pre-wrap">{msg.content}</p>
                                            </div>
                                            {msg.role === 'assistant' && (
                                                <button onClick={() => handleCopy(msg.content)} className="mt-1 text-xs text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 flex items-center gap-1">
                                                    {copied ? <><CheckCheck className="w-3 h-3" /> Copied</> : <><Copy className="w-3 h-3" /> Copy</>}
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                ))}
                                {loading && (
                                    <div className="flex gap-3">
                                        <div className="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400 flex items-center justify-center text-sm font-medium flex-shrink-0">
                                            <Sparkles className="w-4 h-4" />
                                        </div>
                                        <div className="bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 p-3 rounded-xl rounded-tl-none text-sm">
                                            Thinking...
                                        </div>
                                    </div>
                                )}
                            </>
                        )}
                    </div>

                    <div className="border-t border-gray-200 dark:border-gray-700 p-4">
                        <form onSubmit={handleSend} className="flex gap-2">
                            <input
                                type="text"
                                value={input}
                                onChange={(e) => setInput(e.target.value)}
                                placeholder="Ask the AI anything..."
                                disabled={loading}
                                className="flex-1 px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent disabled:opacity-50"
                            />
                            <button type="submit" disabled={loading || !input.trim()} className="px-4 py-2.5 bg-purple-600 text-white rounded-xl hover:bg-purple-700 disabled:opacity-50">
                                <Send className="w-4 h-4" />
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
