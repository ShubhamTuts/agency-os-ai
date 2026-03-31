import React, { createContext, useContext, useState, ReactNode } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
    avatar_url: string;
}

interface AuthContextType {
    user: User | null;
    setUser: React.Dispatch<React.SetStateAction<User | null>>;
    isPro: boolean;
}

declare const aosaiData: {
    userId: number;
    isPro: boolean;
    currentUser?: {
        name?: string;
        email?: string;
        avatar_url?: string;
    };
};

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
    const [user, setUser] = useState<User | null>({
        id: aosaiData?.userId || 0,
        name: aosaiData?.currentUser?.name || 'User',
        email: aosaiData?.currentUser?.email || '',
        avatar_url: aosaiData?.currentUser?.avatar_url || '',
    });
    const isPro = aosaiData?.isPro || false;

    return (
        <AuthContext.Provider value={{ user, setUser, isPro }}>
            {children}
        </AuthContext.Provider>
    );
}

export function useAuth() {
    const context = useContext(AuthContext);
    if (context === undefined) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
}

export function usePermissions() {
    const { isPro } = useAuth();
    return { isPro };
}

