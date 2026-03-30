import React, { useState, useEffect, useRef } from 'react';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPost } from '../services/api';
import { Send, Search, AtSign } from 'lucide-react';

interface Message {
    id: number;
    content: string;
    user_id: number;
    user_name: string;
    created_at: string;
    project_id: number;
    project_name: string;
    mentions: number[];
}

interface User {
    id: number;
    name: string;
    email: string;
}

export default function Messages() {
    const [messages, setMessages] = useState<Message[]>([]);
    const [loading, setLoading] = useState(true);
    const [newMsg, setNewMsg] = useState('');
    const [search, setSearch] = useState('');
    const [users, setUsers] = useState<User[]>([]);
    const [mentionSearch, setMentionSearch] = useState('');
    const [showMentions, setShowMentions] = useState(false);
    const [cursor, setCursor] = useState(0);
    const [sending, setSending] = useState(false);
    const bottomRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        Promise.all([
            apiGet<Message[]>('/aosai/v1/messages').catch(() => []),
            apiGet<User[]>('/aosai/v1/users/list').catch(() => []),
        ]).then(([m, u]) => {
            setMessages(Array.isArray(m) ? m : []);
            setUsers(Array.isArray(u) ? u : []);
        }).finally(() => setLoading(false));
    }, []);

    useEffect(() => { bottomRef.current?.scrollIntoView({ behavior: 'smooth' }); }, [messages]);

    async function handleSend(e?: React.FormEvent) {
        e?.preventDefault();
        if (!newMsg.trim()) return;
        setSending(true);
        try {
            const res = await apiPost<Message>('/aosai/v1/messages', { content: newMsg });
            setMessages(prev => [...prev, res]);
            setNewMsg('');
        } catch (err: any) { alert(err.message); }
        finally { setSending(false); }
    }

    const searchTerm = search.toLowerCase();
    const filtered = messages.filter((m) => {
        if (!searchTerm) return true;
        return (m.content || '').toLowerCase().includes(searchTerm)
            || (m.project_name || '').toLowerCase().includes(searchTerm)
            || (m.user_name || '').toLowerCase().includes(searchTerm);
    });

    function insertMention(user: User) {
        const before = newMsg.slice(0, cursor);
        const after = newMsg.slice(cursor);
        const nextValue = before + `@${user.name} ` + after;
        const nextCursor = before.length + user.name.length + 2;
        setNewMsg(nextValue);
        setCursor(nextCursor);
        setShowMentions(false);
        setMentionSearch('');
        window.setTimeout(() => {
            inputRef.current?.focus();
            inputRef.current?.setSelectionRange(nextCursor, nextCursor);
        }, 0);
    }

    function handleInputChange(val: string, pos: number) {
        setNewMsg(val);
        setCursor(pos);
        const textBeforeCursor = val.slice(0, pos);
        const mentionMatch = textBeforeCursor.match(/@([^@]*)$/);
        if (mentionMatch) {
            setMentionSearch(mentionMatch[1]);
            setShowMentions(true);
        } else {
            setShowMentions(false);
        }
    }

    return (
        <DashboardLayout>
            <div className="max-w-4xl mx-auto h-[calc(100vh-120px)] flex flex-col">
                <div className="flex items-center justify-between mb-4">
                    <div>
                        <h1 className="text-xl font-bold text-gray-900 dark:text-white">Messages</h1>
                        <p className="text-xs text-gray-500">Team communication hub</p>
                    </div>
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input type="text" placeholder="Search messages..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-9 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-sm w-64" />
                    </div>
                </div>

                <div className="flex-1 bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 flex flex-col overflow-hidden">
                    <div className="flex-1 overflow-y-auto p-4 space-y-4">
                        {loading ? <LoadingSpinner /> : filtered.length === 0 ? (
                            <div className="text-center py-16 text-sm text-gray-500">No messages yet. Start a conversation!</div>
                        ) : filtered.map((msg) => (
                            <div key={msg.id} className="flex gap-3">
                                <div className="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 flex items-center justify-center text-sm font-medium flex-shrink-0">
                                    {msg.user_name?.charAt(0) || '?'}
                                </div>
                                <div className="flex-1 min-w-0">
                                    <div className="flex items-center gap-2 mb-1">
                                        <span className="text-sm font-medium text-gray-900 dark:text-white">{msg.user_name}</span>
                                        <span className="text-xs text-gray-400">{msg.project_name}</span>
                                        <span className="text-xs text-gray-400">{new Date(msg.created_at).toLocaleString()}</span>
                                    </div>
                                    <p className="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{msg.content}</p>
                                </div>
                            </div>
                        ))}
                        <div ref={bottomRef} />
                    </div>

                    <div className="border-t border-gray-200 dark:border-gray-700 p-4 relative">
                        {showMentions && (
                            <div className="absolute bottom-full mb-1 left-4 right-4 bg-white dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                {users.filter(u => u.name.toLowerCase().includes(mentionSearch.toLowerCase())).map(u => (
                                    <button key={u.id} onClick={() => insertMention(u)} className="w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-gray-600 flex items-center gap-2">
                                        <span className="w-5 h-5 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 flex items-center justify-center text-xs">{u.name.charAt(0)}</span>
                                        <span>{u.name}</span>
                                    </button>
                                ))}
                            </div>
                        )}
                        <form onSubmit={handleSend} className="flex gap-2">
                            <input
                                ref={inputRef}
                                type="text"
                                value={newMsg}
                                onChange={(e) => handleInputChange(e.target.value, e.target.selectionStart ?? e.target.value.length)}
                                placeholder="Type a message... Use @ to mention"
                                className="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:ring-2 focus:ring-primary-500"
                            />
                            <button type="submit" disabled={sending} className="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
                                <Send className="w-4 h-4" />
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </DashboardLayout>
    );
}
