import React from 'react';
import { getAnimatedBrandLogo } from '../../utils/branding';

export function LoadingSpinner({
    size = 'md',
    label = 'Loading workspace',
}: {
    size?: 'sm' | 'md' | 'lg';
    label?: string;
}) {
    const animatedLogo = getAnimatedBrandLogo();
    const sizeClasses = {
        sm: 'w-16',
        md: 'w-28',
        lg: 'w-36',
    };

    return (
        <div className="flex items-center justify-center p-8">
            <div className="flex flex-col items-center gap-4 rounded-[28px] border border-gray-200/80 bg-white/90 px-6 py-5 text-center shadow-[0_18px_40px_rgba(15,23,42,0.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-800/90">
                {animatedLogo ? (
                    <img
                        src={animatedLogo}
                        alt="Agency OS AI"
                        className={`${sizeClasses[size]} h-auto`}
                    />
                ) : (
                    <div
                        className={`${sizeClasses[size]} aspect-square rounded-full border-4 border-gray-200 border-t-primary-600 animate-spin`}
                    />
                )}
                <p className="text-sm font-medium text-gray-500 dark:text-gray-300">{label}</p>
            </div>
        </div>
    );
}
