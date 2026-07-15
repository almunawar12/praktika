import '@inertiajs/core';
import type { Auth } from '@/types/auth';
import type { NavGroup } from '@/types/nav';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebar: NavGroup[];
            flash: {
                status: string | null;
                success: string | null;
                error: string | null;
            };
            [key: string]: unknown;
        };
    }
}
