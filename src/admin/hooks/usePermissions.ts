import { useState, useEffect } from 'react';

interface Permissions {
    isPro: boolean;
    canManageProjects: boolean;
    canManageTeam: boolean;
}

export function usePermissions(): Permissions {
    const [permissions] = useState<Permissions>(() => ({
        isPro: !!(window as any).aosaiData?.isPro,
        canManageProjects: true,
        canManageTeam: true,
    }));

    return permissions;
}

export function useCurrentUser() {
    return {
        id: (window as any).aosaiData?.userId ?? 0,
    };
}

export function useDebounce<T>(value: T, delay: number): T {
    const [debounced, setDebounced] = useState<T>(value);
    useEffect(() => {
        const timer = setTimeout(() => setDebounced(value), delay);
        return () => clearTimeout(timer);
    }, [value, delay]);
    return debounced;
}
