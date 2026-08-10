import { Link } from '@inertiajs/react';
import type { CSSProperties, PropsWithChildren } from 'react';
import { PraktikaMark } from '@/components/public/praktika-mark';

type AuthLayoutProps = PropsWithChildren<{
    title: string;
    description?: string;
}>;

const roles = [
    'Super Admin',
    'Admin PKL',
    'Guru Pembimbing',
    'Pembimbing Industri',
    'Siswa',
];

/**
 * Re-points shadcn's theme vars at the --pk-* public-page palette, scoped to
 * this layout only. Button/Input/Alert read var(--primary) etc. as-is, so the
 * auth flow matches the landing page without touching those components.
 */
const themeOverride = {
    '--background': 'var(--pk-paper)',
    '--foreground': 'var(--pk-ink)',
    '--card': 'var(--pk-sheet)',
    '--card-foreground': 'var(--pk-ink)',
    '--muted-foreground': 'var(--pk-ink-soft)',
    '--primary': 'var(--pk-pen)',
    '--primary-foreground': 'var(--pk-pen-fg)',
    '--border': 'var(--pk-rule)',
    '--input': 'var(--pk-rule)',
    '--ring': 'var(--pk-pen)',
} as CSSProperties;

export default function AuthLayout({
    title,
    description,
    children,
}: AuthLayoutProps) {
    return (
        <div
            style={themeOverride}
            className="grid min-h-svh bg-[var(--pk-paper)] font-sans text-[var(--pk-ink)] antialiased lg:grid-cols-2"
        >
            <div className="pk-grid relative hidden flex-col justify-between overflow-hidden border-r border-[var(--pk-rule)] p-14 lg:flex">
                <div className="absolute inset-0 bg-gradient-to-b from-[var(--pk-paper)]/30 via-transparent to-[var(--pk-paper)]" />

                <Link
                    href="/"
                    className="relative z-10 flex items-center gap-2.5 self-start rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pk-pen)]"
                >
                    <PraktikaMark className="h-8 w-8" />
                    <span className="font-display text-[1.35rem] leading-none font-extrabold tracking-tight">
                        Praktika
                    </span>
                </Link>

                <div className="pk-rise relative z-10">
                    <p className="font-mono text-[0.7rem] tracking-[0.22em] text-[var(--pk-ink-soft)] uppercase">
                        Sistem monitoring PKL SMK
                    </p>
                    <p className="mt-4 max-w-md font-display text-[clamp(1.7rem,3vw,2.3rem)] leading-[1.08] font-extrabold tracking-[-0.02em]">
                        Setiap hari yang tercatat adalah hari yang bisa
                        dipertanggungjawabkan.
                    </p>
                </div>

                <div className="relative z-10">
                    <p className="font-mono text-[0.66rem] tracking-[0.16em] text-[var(--pk-ink-soft)] uppercase">
                        Akun dibuat oleh Admin PKL untuk
                    </p>
                    <p className="mt-3 text-sm leading-relaxed">
                        {roles.join(' · ')}
                    </p>
                </div>
            </div>

            <div className="flex flex-col items-center justify-center gap-6 p-6 sm:p-10">
                <Link
                    href="/"
                    className="flex items-center gap-2.5 rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pk-pen)] lg:hidden"
                >
                    <PraktikaMark className="h-8 w-8" />
                    <span className="font-display text-[1.35rem] leading-none font-extrabold tracking-tight">
                        Praktika
                    </span>
                </Link>

                <div className="w-full max-w-sm">
                    <div className="rounded-2xl border border-[var(--pk-rule)] bg-[var(--pk-sheet)] p-6 shadow-[0_28px_60px_-32px_rgba(15,26,43,0.35)] sm:p-8">
                        <div className="flex flex-col gap-1">
                            <h1 className="font-display text-xl font-bold tracking-tight">
                                {title}
                            </h1>
                            {description ? (
                                <p className="text-sm text-balance text-[var(--pk-ink-soft)]">
                                    {description}
                                </p>
                            ) : null}
                        </div>

                        <div className="mt-6 flex flex-col gap-6">
                            {children}
                        </div>
                    </div>

                    <p className="mt-6 text-center font-mono text-[0.68rem] tracking-[0.14em] text-[var(--pk-ink-soft)] uppercase">
                        Sistem Monitoring PKL SMK
                    </p>
                </div>
            </div>
        </div>
    );
}
