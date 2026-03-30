import { NavLink } from 'react-router-dom';
import { usePermissions } from '../../hooks/usePermissions';
import { getBrandLogo } from '../../utils/branding';
import {
    LayoutDashboard, FolderKanban, Target,
    MessageSquare, FileText, BarChart3, Users, Settings,
    Sparkles, ChevronLeft, ChevronRight,
    LucideIcon
} from 'lucide-react';

interface SidebarProps {
    collapsed: boolean;
    onToggle: () => void;
}

const navItems: Array<{ to?: string; icon?: LucideIcon; label: string; divider?: boolean }> = [
    { to: '/', icon: LayoutDashboard, label: 'Dashboard' },
    { to: '/projects', icon: FolderKanban, label: 'Projects' },
    { to: '/milestones', icon: Target, label: 'Milestones' },
    { to: '/messages', icon: MessageSquare, label: 'Messages' },
    { to: '/files', icon: FileText, label: 'Files' },
    { to: '/ai', icon: Sparkles, label: 'AI Assistant' },
    { divider: true, label: 'Management' },
    { to: '/reports', icon: BarChart3, label: 'Reports' },
    { to: '/team', icon: Users, label: 'Team' },
    { to: '/settings', icon: Settings, label: 'Settings' },
];

export function Sidebar({ collapsed, onToggle }: SidebarProps) {
    usePermissions(); // hook ready for future use
    const logoSrc = getBrandLogo();

    return (
        <aside className={`
            ${collapsed ? 'w-16' : 'w-64'} 
            bg-white dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700
            flex flex-col transition-all duration-200 ease-in-out h-full
        `}>
            <div className="h-16 flex items-center px-4 gap-3 border-b border-gray-200 dark:border-gray-700">
                {logoSrc ? (
                    <img
                        src={logoSrc}
                        alt="Agency OS AI"
                        className="h-10 w-10 rounded-2xl object-contain shrink-0"
                    />
                ) : (
                    <span className="flex h-10 w-10 items-center justify-center rounded-2xl bg-primary-50 text-sm font-bold text-primary-700 dark:bg-primary-900/40 dark:text-primary-300 shrink-0">AO</span>
                )}
                {!collapsed ? (
                    <div className="min-w-0">
                        <span className="block truncate text-sm font-semibold text-gray-900 dark:text-white">Agency OS AI</span>
                        <span className="block truncate text-xs text-gray-500 dark:text-gray-400">Project workspace</span>
                    </div>
                ) : (
                    <span className="sr-only">Agency OS AI</span>
                )}
                <button
                    onClick={onToggle}
                    className="ml-auto p-1.5 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-500"
                    aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                >
                    {collapsed ? <ChevronRight size={18} /> : <ChevronLeft size={18} />}
                </button>
            </div>
            
            <nav className="flex-1 overflow-y-auto py-4" aria-label="Main navigation">
                {navItems.map((item, index) => {
                    if ('divider' in item && item.divider) {
                        return !collapsed ? (
                            <div key={index} className="px-4 pt-4 pb-2">
                                <span className="text-xs font-semibold text-gray-400 uppercase tracking-wider">
                                    {item.label}
                                </span>
                            </div>
                        ) : <hr key={index} className="my-3 mx-3 border-gray-200 dark:border-gray-700" />;
                    }
                    
                    const Icon = item.icon!;
                    const path = item.to!;

                    return (
                        <NavLink
                            key={path}
                            to={path}
                            end={path === '/'}
                            className={({ isActive }) => `
                                flex items-center gap-3 px-4 py-2.5 mx-2 rounded-lg
                                text-sm font-medium transition-colors
                                ${isActive
                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300'
                                    : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700/50'
                                }
                            `}
                        >
                            <Icon size={20} />
                            {!collapsed && <span>{item.label}</span>}
                        </NavLink>
                    );
                })}
            </nav>
        </aside>
    );
}
