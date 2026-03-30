import React, { useState, useEffect } from 'react';
import { DashboardLayout } from '../components/layout/DashboardLayout';
import { LoadingSpinner } from '../components/ui/LoadingSpinner';
import { apiGet, apiPost } from '../services/api';
import { useAuth } from '../context/AuthContext';

interface UserProfile {
    id: number;
    email: string;
    first_name: string;
    last_name: string;
    display_name: string;
    avatar_url: string;
}

export default function Profile() {
    const { user, setUser } = useAuth();
    const [profile, setProfile] = useState<Partial<UserProfile>>({});
    const [password, setPassword] = useState('');
    const [confirmPassword, setConfirmPassword] = useState('');
    const [loading, setLoading] = useState(true);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');

    useEffect(() => {
        apiGet<UserProfile>('/aosai/v1/profile/me')
            .then(data => {
                setProfile(data);
                setLoading(false);
            })
            .catch(() => setLoading(false));
    }, []);

    async function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        setError('');
        setSuccess('');

        if (password && password !== confirmPassword) {
            setError("Passwords do not match.");
            return;
        }

        setSaving(true);
        try {
            const payload: any = { ...profile };
            if (password) {
                payload.password = password;
            }
            const updatedProfile = await apiPost<UserProfile>('/aosai/v1/profile/me', payload);
            setProfile(updatedProfile);
            if (user) {
                setUser({ ...user, name: updatedProfile.display_name, avatar_url: updatedProfile.avatar_url, email: updatedProfile.email });
            }
            setSuccess('Profile updated successfully!');
            setPassword('');
            setConfirmPassword('');
        } catch (err: any) {
            setError(err.message || 'Failed to update profile.');
        } finally {
            setSaving(false);
        }
    }
    
    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const { name, value } = e.target;
        setProfile(prev => ({...prev, [name]: value}));
    };

    if (loading) {
        return <DashboardLayout><LoadingSpinner /></DashboardLayout>;
    }

    return (
        <DashboardLayout>
            <div className="max-w-2xl mx-auto">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-6">My Profile</h1>
                
                <div className="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
                    <form onSubmit={handleSubmit}>
                        <div className="p-6 space-y-4">
                            <div className="flex items-center gap-4">
                                <img src={profile.avatar_url} alt="Avatar" className="w-16 h-16 rounded-full" />
                                <div>
                                    <h2 className="text-xl font-semibold">{profile.display_name}</h2>
                                    <p className="text-sm text-gray-500">{profile.email}</p>
                                </div>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">First Name</label>
                                    <input type="text" name="first_name" value={profile.first_name || ''} onChange={handleInputChange} className="w-full form-input" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Last Name</label>
                                    <input type="text" name="last_name" value={profile.last_name || ''} onChange={handleInputChange} className="w-full form-input" />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Email</label>
                                <input type="email" name="email" value={profile.email || ''} onChange={handleInputChange} className="w-full form-input" />
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">New Password</label>
                                    <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} className="w-full form-input" placeholder="Leave blank to keep current" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirm Password</label>
                                    <input type="password" value={confirmPassword} onChange={(e) => setConfirmPassword(e.target.value)} className="w-full form-input" />
                                </div>
                            </div>
                            {error && <p className="text-sm text-red-500">{error}</p>}
                            {success && <p className="text-sm text-green-500">{success}</p>}
                        </div>
                        <div className="px-6 py-4 bg-gray-50 dark:bg-gray-800/50 border-t border-gray-200 dark:border-gray-700 flex justify-end">
                            <button type="submit" disabled={saving} className="px-4 py-2 text-sm bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50">
                                {saving ? 'Saving...' : 'Save Changes'}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </DashboardLayout>
    );
}
