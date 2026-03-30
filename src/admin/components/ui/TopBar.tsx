import React, { useState, useRef, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Sparkles, Bell, User as UserIcon, LogOut } from 'lucide-react';
import { useAuth } from '../../context/AuthContext';
import { getBrandLogo } from '../../utils/branding';

export function TopBar() {
    const { user } = useAuth();
    const [menuOpen, setMenuOpen] = useState(false);
    const menuRef = useRef<HTMLDivElement>(null);
    const logoSrc = getBrandLogo();

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
                setMenuOpen(false);
            }
        }
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, [menuRef]);

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
                        Run projects, client work, and support from one workspace.
                    </p>
                </div>
            </div>
            
            <div className="flex items-center gap-2 pl-3">
                <Link
                    to="/ai"
                    className="hidden sm:flex items-center gap-2 px-3 py-2 text-sm font-medium text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/20 rounded-lg transition-colors"
                >
                    <Sparkles size={18} />
                    <span>AI Assistant</span>
                </Link>
                
                <button className="relative p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg">
                    <Bell size={20} />
                    <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" />
                </button>
                
                <div className="relative" ref={menuRef}>
                    <button onClick={() => setMenuOpen(prev => !prev)} className="flex items-center gap-2 p-1 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-full">
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
