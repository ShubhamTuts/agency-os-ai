import React, { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import { Bell, LogOut, Sparkles, User as UserIcon } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { apiGet, apiPost } from '../../services/api';
import { getBrandLogo } from '../../utils/branding';

interface TopBarProps {
    onOpenAiAssistant?: () => void;
}

interface NotificationItem {
    id: number;
    title: string;
    content?: string;
    created_at: string;
    is_read: number;
    type?: string;
}

export function TopBar({ onOpenAiAssistant }: TopBarProps) {
    const { user } = useAuth();
    const [menuOpen, setMenuOpen] = useState(false);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const [notifications, setNotifications] = useState<NotificationItem[]>([]);
    const [notificationsLoading, setNotificationsLoading] = useState(false);
    const [unreadCount, setUnreadCount] = useState(0);
    const menuRef = useRef<HTMLDivElement>(null);
    const notificationsRef = useRef<HTMLDivElement>(null);
    const logoSrc = getBrandLogo();

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
                setMenuOpen(false);
            }
            if (notificationsRef.current && !notificationsRef.current.contains(event.target as Node)) {
                setNotificationsOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    useEffect(() => {
        void refreshUnreadCount();
    }, []);

    async function refreshUnreadCount() {
        try {
            const response = await apiGet<{ count?: number }>('/aosai/v1/notifications/unread-count');
            setUnreadCount(Number(response?.count || 0));
        } catch {
            setUnreadCount(0);
        }
    }

    async function loadNotifications() {
        setNotificationsLoading(true);
        try {
            const response = await apiGet<NotificationItem[]>('/aosai/v1/notifications', { per_page: 8 });
            setNotifications(Array.isArray(response) ? response : []);
        } catch {
            setNotifications([]);
        } finally {
            setNotificationsLoading(false);
        }
    }

    async function toggleNotifications() {
        const nextOpen = !notificationsOpen;
        setNotificationsOpen(nextOpen);
        if (nextOpen) {
            await loadNotifications();
        }
    }

    async function handleMarkAllRead() {
        try {
            await apiPost('/aosai/v1/notifications/read-all');
            setNotifications((prev) => prev.map((item) => ({ ...item, is_read: 1 })));
            setUnreadCount(0);
        } catch {
            // Keep the UI stable even if the network request fails.
        }
    }

    async function handleNotificationClick(notification: NotificationItem) {
        if (notification.is_read) {
            return;
        }

        try {
            await apiPost(`/aosai/v1/notifications/${notification.id}/read`);
            setNotifications((prev) => prev.map((item) => item.id === notification.id ? { ...item, is_read: 1 } : item));
            setUnreadCount((prev) => Math.max(0, prev - 1));
        } catch {
            // Ignore and leave the unread state unchanged.
        }
    }

    return (
        <header className="h-16 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between px-4 md:px-6">
            <div className="flex min-w-0 items-center gap-3 md:gap-4">
                <div className="flex items-center gap-3 min-w-0">
                    {logoSrc ? (
                        <img
                            src={logoSrc}
                            alt="Agency OS AI"
                            className="h-10 w-10 rounded-2xl object-contain shrink-0"
                        />
                    ) : (
                        <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary-50 text-sm font-bold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300 shrink-0">
                            AO
                        </div>
                    )}
                    <div className="hidden md:block h-8 w-px bg-gray-200 dark:bg-gray-700" />
                </div>
                <div className="min-w-0">
                    <h1 className="truncate text-lg font-semibold text-gray-900 dark:text-white">
                        Welcome back
                    </h1>
                    <p className="hidden text-xs text-gray-500 md:block">
                        Run projects, client work, support, and AI-guided execution from one workspace.
                    </p>
                </div>
            </div>

            <div className="flex items-center gap-2 pl-3">
                <button
                    type="button"
                    onClick={onOpenAiAssistant}
                    className="hidden sm:flex items-center gap-2 px-3 py-2 text-sm font-medium text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-lg transition-colors"
                >
                    <Sparkles size={18} />
                    <span>Quick AI</span>
                </button>

                <div className="relative" ref={notificationsRef}>
                    <button
                        type="button"
                        onClick={() => void toggleNotifications()}
                        className="relative p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                        aria-label="Open notifications"
                    >
                        <Bell size={20} />
                        {unreadCount > 0 && (
                            <span className="absolute -right-1 -top-1 min-w-[18px] rounded-full bg-rose-500 px-1.5 py-0.5 text-center text-[10px] font-semibold text-white">
                                {unreadCount > 9 ? '9+' : unreadCount}
                            </span>
                        )}
                    </button>

                    {notificationsOpen && (
                        <div className="absolute right-0 mt-2 w-[360px] overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800">
                            <div className="flex items-center justify-between border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                                <div>
                                    <p className="text-sm font-semibold text-gray-900 dark:text-white">Notifications</p>
                                    <p className="text-xs text-gray-500">Recent workspace updates and assignments.</p>
                                </div>
                                <button
                                    type="button"
                                    onClick={() => void handleMarkAllRead()}
                                    className="text-xs font-medium text-primary-600 hover:text-primary-700"
                                >
                                    Mark all read
                                </button>
                            </div>

                            <div className="max-h-[420px] overflow-y-auto">
                                {notificationsLoading ? (
                                    <div className="px-4 py-8 text-sm text-gray-500">Loading notifications...</div>
                                ) : notifications.length === 0 ? (
                                    <div className="px-4 py-8 text-sm text-gray-500">No notifications yet.</div>
                                ) : (
                                    notifications.map((notification) => (
                                        <button
                                            key={notification.id}
                                            type="button"
                                            onClick={() => void handleNotificationClick(notification)}
                                            className={`block w-full border-b border-gray-100 px-4 py-3 text-left last:border-b-0 dark:border-gray-700 ${notification.is_read ? 'bg-white dark:bg-gray-800' : 'bg-amber-50/60 dark:bg-amber-900/10'}`}
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <div>
                                                    <p className="text-sm font-medium text-gray-900 dark:text-white">{notification.title}</p>
                                                    {notification.content && <p className="mt-1 text-xs text-gray-500">{notification.content}</p>}
                                                </div>
                                                {!notification.is_read && <span className="mt-1 h-2.5 w-2.5 rounded-full bg-primary-500" />}
                                            </div>
                                            <p className="mt-2 text-[11px] uppercase tracking-[0.14em] text-gray-400">
                                                {notification.type?.replace(/_/g, ' ') || 'workspace'} · {new Date(notification.created_at).toLocaleString()}
                                            </p>
                                        </button>
                                    ))
                                )}
                            </div>
                        </div>
                    )}
                </div>

                <div className="relative" ref={menuRef}>
                    <button onClick={() => setMenuOpen((prev) => !prev)} className="flex items-center gap-2 p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full">
                        {user?.avatar_url ? (
                            <img src={user.avatar_url} alt={user?.name} className="w-8 h-8 rounded-full" />
                        ) : (
                            <div className="w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-900/30 text-primary-700 dark:text-primary-400 flex items-center justify-center text-sm font-medium">
                                {user?.name?.charAt(0) || 'U'}
                            </div>
                        )}
                    </button>
                    {menuOpen && (
                        <div className="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-lg shadow-lg border dark:border-gray-700 py-1">
                            <Link to="/profile" className="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <UserIcon size={16} />
                                My Profile
                            </Link>
                            <a href={(window as any).aosaiData?.logoutUrl} className="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
                                <LogOut size={16} />
                                Logout
                            </a>
                        </div>
                    )}
                </div>
            </div>
        </header>
    );
}
