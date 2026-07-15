import { Link } from '@inertiajs/react';
import { GraduationCap } from 'lucide-react';
import type { PropsWithChildren } from 'react';

type AuthLayoutProps = PropsWithChildren<{
    title: string;
    description?: string;
}>;

export default function AuthLayout({
    title,
    description,
    children,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-4 md:p-8">
            <div className="flex w-full max-w-sm flex-col gap-6">
                <Link
                    href="/"
                    className="flex items-center gap-2 self-center font-medium text-foreground"
                >
                    <span className="flex size-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
                        <GraduationCap className="size-5" aria-hidden="true" />
                    </span>
                    Praktika
                </Link>

                <div className="flex flex-col gap-6 rounded-xl border bg-card p-6 text-card-foreground shadow-sm md:p-8">
                    <div className="flex flex-col gap-1 text-center">
                        <h1 className="text-xl font-semibold tracking-tight">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-sm text-balance text-muted-foreground">
                                {description}
                            </p>
                        ) : null}
                    </div>

                    {children}
                </div>

                <p className="text-center text-xs text-muted-foreground">
                    Sistem Monitoring PKL SMK
                </p>
            </div>
        </div>
    );
}
