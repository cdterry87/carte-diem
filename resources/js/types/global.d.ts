import type { Auth } from '@/types/auth';
import type { Workspace } from '@/types/domain';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            tagline: string;
            taglineLong: string;
            auth: Auth;
            workspaces: Workspace[];
            unreadNotifications: number;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
