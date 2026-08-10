import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { PraktikaMark } from '@/components/public/praktika-mark';

const navigation = [
    { label: 'Alur PKL', href: '/#alur' },
    { label: 'Modul', href: '/#modul' },
    { label: 'Panduan', href: '/panduan' },
];

type PublicLayoutProps = PropsWithChildren<{
    /** Marks the current top-level page so its nav item reads as active. */
    active?: 'beranda' | 'panduan';
}>;

export function PublicLayout({
    children,
    active = 'beranda',
}: PublicLayoutProps) {
    return (
        <div className="min-h-screen bg-[var(--pk-paper)] font-sans text-[var(--pk-ink)] antialiased">
            <a
                href="#konten"
                className="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-md focus:bg-[var(--pk-ink)] focus:px-4 focus:py-2 focus:text-sm focus:text-[var(--pk-sheet)]"
            >
                Lompat ke konten
            </a>

            <header className="sticky top-0 z-40 border-b border-[var(--pk-rule)] bg-[var(--pk-paper)]/85 backdrop-blur-md">
                <div className="mx-auto flex h-16 w-full max-w-6xl items-center gap-6 px-5 sm:px-8">
                    <Link
                        href="/"
                        className="flex items-center gap-2.5 rounded-md focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pk-pen)]"
                    >
                        <PraktikaMark className="h-8 w-8" />
                        <span className="font-display text-[1.35rem] leading-none font-extrabold tracking-tight">
                            Praktika
                        </span>
                    </Link>

                    <nav className="ml-auto hidden items-center gap-7 md:flex">
                        {navigation.map((item) => (
                            <Link
                                key={item.href}
                                href={item.href}
                                className={`font-mono text-[0.7rem] tracking-[0.14em] uppercase transition-colors hover:text-[var(--pk-ink)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pk-pen)] ${
                                    active === 'panduan' &&
                                    item.href === '/panduan'
                                        ? 'text-[var(--pk-ink)]'
                                        : 'text-[var(--pk-ink-soft)]'
                                }`}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <Link
                        href="/login"
                        className="ml-auto rounded-full bg-[var(--pk-ink)] px-5 py-2 font-mono text-[0.7rem] tracking-[0.14em] text-[var(--pk-sheet)] uppercase transition-transform hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pk-pen)] md:ml-0"
                    >
                        Masuk
                    </Link>
                </div>
            </header>

            <main id="konten">{children}</main>

            <footer className="border-t border-[var(--pk-rule)]">
                <div className="mx-auto grid w-full max-w-6xl gap-8 px-5 py-12 sm:px-8 md:grid-cols-[1.4fr_1fr_1fr]">
                    <div>
                        <div className="flex items-center gap-2.5">
                            <PraktikaMark className="h-7 w-7" />
                            <span className="font-display text-lg font-extrabold tracking-tight">
                                Praktika
                            </span>
                        </div>
                        <p className="mt-3 max-w-xs text-sm leading-relaxed text-[var(--pk-ink-soft)]">
                            Monitoring Praktik Kerja Lapangan untuk SMK —
                            penempatan, absensi, jurnal, monitoring, dan
                            penilaian dalam satu buku.
                        </p>
                    </div>

                    <div>
                        <p className="font-mono text-[0.68rem] tracking-[0.16em] text-[var(--pk-ink-soft)] uppercase">
                            Halaman
                        </p>
                        <ul className="mt-3 space-y-2 text-sm">
                            {navigation.map((item) => (
                                <li key={item.href}>
                                    <Link
                                        href={item.href}
                                        className="text-[var(--pk-ink-soft)] hover:text-[var(--pk-ink)]"
                                    >
                                        {item.label}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div>
                        <p className="font-mono text-[0.68rem] tracking-[0.16em] text-[var(--pk-ink-soft)] uppercase">
                            Akses
                        </p>
                        <ul className="mt-3 space-y-2 text-sm">
                            <li>
                                <Link
                                    href="/login"
                                    className="text-[var(--pk-ink-soft)] hover:text-[var(--pk-ink)]"
                                >
                                    Masuk
                                </Link>
                            </li>
                            <li>
                                <Link
                                    href="/forgot-password"
                                    className="text-[var(--pk-ink-soft)] hover:text-[var(--pk-ink)]"
                                >
                                    Lupa kata sandi
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>

                <div className="border-t border-[var(--pk-rule)]">
                    <div className="mx-auto flex w-full max-w-6xl flex-col gap-2 px-5 py-5 font-mono text-[0.68rem] tracking-[0.1em] text-[var(--pk-ink-soft)] uppercase sm:flex-row sm:items-center sm:justify-between sm:px-8">
                        <span>Praktika — Sistem Monitoring PKL SMK</span>
                        <span>Akun dibuat oleh admin sekolah</span>
                    </div>
                </div>
            </footer>
        </div>
    );
}
