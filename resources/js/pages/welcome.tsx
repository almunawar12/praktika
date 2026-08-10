import { Head, Link } from '@inertiajs/react';
import {
    BookOpenCheck,
    CalendarCheck,
    ClipboardList,
    FileSpreadsheet,
    MapPin,
    Route as RouteIcon,
} from 'lucide-react';
import { PublicLayout } from '@/components/public/public-layout';

/** PKL runs as an ordered term, so these stages are numbered on purpose. */
const stages = [
    {
        stage: 'Penempatan',
        what: 'Admin PKL memetakan siswa ke DU/DI, menetapkan periode, dan menunjuk guru pembimbing.',
        who: 'Admin PKL',
    },
    {
        stage: 'Absensi',
        what: 'Siswa check in dan check out dengan foto dan titik lokasi yang terekam otomatis.',
        who: 'Siswa',
    },
    {
        stage: 'Jurnal harian',
        what: 'Siswa menulis kegiatan hari itu. Guru menyetujui atau mengembalikannya dengan catatan.',
        who: 'Siswa & Guru',
    },
    {
        stage: 'Monitoring',
        what: 'Guru mencatat kunjungan ke tempat PKL beserta waktu, foto, dan temuan di lapangan.',
        who: 'Guru Pembimbing',
    },
    {
        stage: 'Penilaian',
        what: 'Nilai dari pembimbing industri dan guru digabung menjadi nilai akhir secara otomatis.',
        who: 'Pembimbing & Guru',
    },
];

const modules = [
    {
        icon: RouteIcon,
        title: 'Penempatan',
        body: 'Satu siswa, satu penempatan aktif per tahun ajaran. Periode berakhir, penempatan menutup sendiri.',
    },
    {
        icon: MapPin,
        title: 'Absensi berfoto',
        body: 'Check in dan check out menyimpan waktu, koordinat, dan foto — bukti kehadiran yang sulit dikarang.',
    },
    {
        icon: BookOpenCheck,
        title: 'Jurnal harian',
        body: 'Tiga status yang jelas: menunggu, disetujui, ditolak. Catatan penolakan selalu ikut terkirim.',
    },
    {
        icon: ClipboardList,
        title: 'Monitoring guru',
        body: 'Kunjungan tercatat dengan jam kedatangan dan foto di lokasi, bukan sekadar ceklis.',
    },
    {
        icon: CalendarCheck,
        title: 'Penilaian',
        body: 'Nilai industri dan nilai guru masuk terpisah, nilai akhir dihitung sistem tanpa rekap manual.',
    },
    {
        icon: FileSpreadsheet,
        title: 'Laporan',
        body: 'Rekap kehadiran, jurnal, dan nilai per kelas atau per DU/DI, siap dicetak untuk arsip sekolah.',
    },
];

const roles = [
    {
        role: 'Super Admin',
        line: 'Mengelola pengguna, peran, dan pengaturan sistem.',
    },
    {
        role: 'Admin PKL',
        line: 'Mengelola data master, penempatan, dan laporan sekolah.',
    },
    {
        role: 'Guru Pembimbing',
        line: 'Memvalidasi jurnal, mencatat monitoring, dan memberi nilai.',
    },
    {
        role: 'Pembimbing Industri',
        line: 'Menilai siswa yang dibimbing di tempat kerja.',
    },
    {
        role: 'Siswa',
        line: 'Absen harian, menulis jurnal, dan melihat nilai sendiri.',
    },
];

export default function Welcome() {
    return (
        <PublicLayout active="beranda">
            <Head title="Monitoring PKL SMK" />

            {/* Hero — the journal sheet is the thesis, so it sits beside the headline. */}
            <section className="pk-grid relative overflow-hidden border-b border-[var(--pk-rule)]">
                <div className="absolute inset-0 bg-gradient-to-b from-[var(--pk-paper)]/40 via-[var(--pk-paper)]/80 to-[var(--pk-paper)]" />

                <div className="relative mx-auto grid w-full max-w-6xl gap-14 px-5 py-16 sm:px-8 sm:py-24 lg:grid-cols-[1.05fr_1fr] lg:items-center">
                    <div className="pk-rise">
                        <p className="font-mono text-[0.7rem] tracking-[0.22em] text-[var(--pk-ink-soft)] uppercase">
                            Sistem monitoring praktik kerja lapangan
                        </p>

                        <h1 className="mt-5 font-display text-[clamp(2.6rem,7vw,4.6rem)] leading-[0.94] font-extrabold tracking-[-0.035em]">
                            Buku PKL sekolah,
                            <br />
                            <span className="text-[var(--pk-pen)]">
                                terisi setiap hari.
                            </span>
                        </h1>

                        <p className="mt-6 max-w-lg text-lg leading-relaxed text-[var(--pk-ink-soft)]">
                            Praktika mencatat penempatan, absensi, jurnal
                            harian, kunjungan guru, sampai nilai akhir siswa —
                            dari hari pertama di DU/DI sampai penarikan.
                        </p>

                        <div className="mt-9 flex flex-wrap items-center gap-3">
                            <Link
                                href="/login"
                                className="rounded-full bg-[var(--pk-pen)] px-7 py-3.5 text-sm font-semibold text-[var(--pk-pen-fg)] transition-transform hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pk-pen)]"
                            >
                                Masuk ke Praktika
                            </Link>
                            <Link
                                href="/panduan"
                                className="rounded-full border border-[var(--pk-ink)]/25 px-7 py-3.5 text-sm font-semibold transition-colors hover:border-[var(--pk-ink)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pk-pen)]"
                            >
                                Baca panduan
                            </Link>
                        </div>

                        <p className="mt-6 max-w-sm text-sm text-[var(--pk-ink-soft)]">
                            Tidak ada pendaftaran publik. Akun siswa, guru, dan
                            pembimbing industri dibuat oleh Admin PKL.
                        </p>
                    </div>

                    <JournalSheet />
                </div>
            </section>

            {/* Alur — a genuine ordered process, rendered as a ledger. */}
            <section
                id="alur"
                className="scroll-mt-20 border-b border-[var(--pk-rule)]"
            >
                <div className="mx-auto w-full max-w-6xl px-5 py-16 sm:px-8 sm:py-20">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <div>
                            <p className="font-mono text-[0.7rem] tracking-[0.22em] text-[var(--pk-ink-soft)] uppercase">
                                Alur PKL
                            </p>
                            <h2 className="mt-3 font-display text-[clamp(1.9rem,4vw,2.8rem)] leading-tight font-extrabold tracking-[-0.03em]">
                                Lima tahap, satu berkas
                            </h2>
                        </div>
                        <p className="max-w-sm text-sm leading-relaxed text-[var(--pk-ink-soft)]">
                            Setiap tahap menghasilkan catatan yang dipakai tahap
                            berikutnya. Tidak ada rekap ulang di akhir semester.
                        </p>
                    </div>

                    <ol className="mt-10 border-t border-[var(--pk-rule)]">
                        {stages.map((item, index) => (
                            <li
                                key={item.stage}
                                className="group grid gap-x-6 gap-y-2 border-b border-[var(--pk-rule)] py-6 sm:grid-cols-[3rem_minmax(0,12rem)_1fr_auto] sm:items-baseline"
                            >
                                <span className="font-mono text-sm text-[var(--pk-ink-soft)] tabular-nums">
                                    {String(index + 1).padStart(2, '0')}
                                </span>
                                <h3 className="font-display text-xl font-bold tracking-tight">
                                    {item.stage}
                                </h3>
                                <p className="text-[0.95rem] leading-relaxed text-[var(--pk-ink-soft)]">
                                    {item.what}
                                </p>
                                <span className="font-mono text-[0.68rem] tracking-[0.14em] text-[var(--pk-ink-soft)] uppercase sm:text-right">
                                    {item.who}
                                </span>
                            </li>
                        ))}
                    </ol>
                </div>
            </section>

            {/* Modul */}
            <section
                id="modul"
                className="scroll-mt-20 border-b border-[var(--pk-rule)]"
            >
                <div className="mx-auto w-full max-w-6xl px-5 py-16 sm:px-8 sm:py-20">
                    <p className="font-mono text-[0.7rem] tracking-[0.22em] text-[var(--pk-ink-soft)] uppercase">
                        Modul
                    </p>
                    <h2 className="mt-3 max-w-2xl font-display text-[clamp(1.9rem,4vw,2.8rem)] leading-tight font-extrabold tracking-[-0.03em]">
                        Yang dikerjakan Praktika setiap hari
                    </h2>

                    <div className="mt-10 grid gap-px overflow-hidden rounded-2xl border border-[var(--pk-rule)] bg-[var(--pk-rule)] sm:grid-cols-2 lg:grid-cols-3">
                        {modules.map((item) => (
                            <article
                                key={item.title}
                                className="bg-[var(--pk-sheet)] p-7 transition-colors hover:bg-[var(--pk-paper)]"
                            >
                                <item.icon
                                    className="h-5 w-5 text-[var(--pk-pen)]"
                                    strokeWidth={1.75}
                                    aria-hidden="true"
                                />
                                <h3 className="mt-4 font-display text-lg font-bold tracking-tight">
                                    {item.title}
                                </h3>
                                <p className="mt-2 text-[0.92rem] leading-relaxed text-[var(--pk-ink-soft)]">
                                    {item.body}
                                </p>
                            </article>
                        ))}
                    </div>
                </div>
            </section>

            {/* Peran */}
            <section className="border-b border-[var(--pk-rule)]">
                <div className="mx-auto grid w-full max-w-6xl gap-10 px-5 py-16 sm:px-8 sm:py-20 lg:grid-cols-[minmax(0,20rem)_1fr]">
                    <div>
                        <p className="font-mono text-[0.7rem] tracking-[0.22em] text-[var(--pk-ink-soft)] uppercase">
                            Peran
                        </p>
                        <h2 className="mt-3 font-display text-[clamp(1.9rem,4vw,2.6rem)] leading-tight font-extrabold tracking-[-0.03em]">
                            Setiap orang melihat bagiannya
                        </h2>
                        <p className="mt-4 text-sm leading-relaxed text-[var(--pk-ink-soft)]">
                            Hak akses ditentukan peran. Siswa tidak melihat data
                            siswa lain, dan pembimbing industri hanya melihat
                            siswa di perusahaannya.
                        </p>
                    </div>

                    <dl className="border-t border-[var(--pk-rule)]">
                        {roles.map((item) => (
                            <div
                                key={item.role}
                                className="grid gap-1 border-b border-[var(--pk-rule)] py-5 sm:grid-cols-[minmax(0,13rem)_1fr] sm:items-baseline sm:gap-6"
                            >
                                <dt className="font-mono text-[0.72rem] tracking-[0.14em] uppercase">
                                    {item.role}
                                </dt>
                                <dd className="text-[0.95rem] text-[var(--pk-ink-soft)]">
                                    {item.line}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </div>
            </section>

            {/* Penutup */}
            <section className="pk-grid relative overflow-hidden">
                <div className="absolute inset-0 bg-gradient-to-b from-[var(--pk-paper)] via-[var(--pk-paper)]/70 to-[var(--pk-paper)]" />
                <div className="relative mx-auto w-full max-w-6xl px-5 py-20 text-center sm:px-8 sm:py-28">
                    <h2 className="mx-auto max-w-3xl font-display text-[clamp(2rem,5vw,3.4rem)] leading-[1.02] font-extrabold tracking-[-0.035em]">
                        Mulai dari halaman masuk
                    </h2>
                    <p className="mx-auto mt-5 max-w-md text-[var(--pk-ink-soft)]">
                        Gunakan email dan kata sandi dari Admin PKL sekolah
                        Anda. Belum tahu langkahnya? Panduannya per peran.
                    </p>
                    <div className="mt-9 flex flex-wrap justify-center gap-3">
                        <Link
                            href="/login"
                            className="rounded-full bg-[var(--pk-ink)] px-7 py-3.5 text-sm font-semibold text-[var(--pk-sheet)] transition-transform hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pk-pen)]"
                        >
                            Masuk ke Praktika
                        </Link>
                        <Link
                            href="/panduan"
                            className="rounded-full border border-[var(--pk-ink)]/25 px-7 py-3.5 text-sm font-semibold transition-colors hover:border-[var(--pk-ink)] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pk-pen)]"
                        >
                            Baca panduan
                        </Link>
                    </div>
                </div>
            </section>
        </PublicLayout>
    );
}

/**
 * Signature element: a jurnal harian sheet as it exists on paper — ruled lines,
 * a meta header, and the pembimbing's approval stamp pressed on top.
 */
function JournalSheet() {
    return (
        <div className="pk-rise relative [animation-delay:120ms]">
            <div className="rotate-[0.6deg] rounded-2xl border border-[var(--pk-rule)] bg-[var(--pk-sheet)] shadow-[0_28px_60px_-32px_rgba(15,26,43,0.45)]">
                <div className="flex items-center justify-between border-b border-[var(--pk-rule)] px-6 py-4">
                    <span className="font-mono text-[0.68rem] tracking-[0.18em] uppercase">
                        Jurnal harian
                    </span>
                    <span className="font-mono text-[0.68rem] text-[var(--pk-ink-soft)] tabular-nums">
                        10 / 08 / 2026
                    </span>
                </div>

                <dl className="grid grid-cols-2 gap-x-6 gap-y-3 border-b border-[var(--pk-rule)] px-6 py-5">
                    {[
                        ['Nama', 'Rifki Maulana'],
                        ['Kelas', 'XII RPL 2'],
                        ['DU / DI', 'CV Sinar Teknologi'],
                        ['Minggu ke', '06'],
                    ].map(([label, value]) => (
                        <div key={label}>
                            <dt className="font-mono text-[0.62rem] tracking-[0.16em] text-[var(--pk-ink-soft)] uppercase">
                                {label}
                            </dt>
                            <dd className="mt-1 text-sm font-medium">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>

                <div className="px-6 py-5">
                    <p className="font-mono text-[0.62rem] tracking-[0.16em] text-[var(--pk-ink-soft)] uppercase">
                        Kegiatan
                    </p>
                    <div className="pk-ruled mt-3 [--pk-line:2rem]">
                        <p className="text-[0.95rem] leading-8">
                            Memasang modul cetak faktur pada aplikasi kasir
                            klien, menguji hasil cetak di dua printer, lalu
                            merapikan dokumentasi rilis bersama tim.
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 border-t border-[var(--pk-rule)] px-6 py-4">
                    <Chip tone="hadir">Hadir 06.58</Chip>
                    <Chip>−6.9147, 107.6098</Chip>
                </div>
            </div>

            {/* The stamp lands after the sheet settles. */}
            <div className="pk-stamp-in absolute -right-1 -bottom-7 sm:-right-5">
                <div className="rounded-xl border-[3px] border-[var(--pk-stamp)] bg-[var(--pk-sheet)]/80 px-5 py-2.5 text-center shadow-sm backdrop-blur-[2px]">
                    <p className="font-display text-lg leading-none font-extrabold tracking-[0.08em] text-[var(--pk-stamp)] uppercase">
                        Disetujui
                    </p>
                    <p className="mt-1.5 font-mono text-[0.55rem] tracking-[0.16em] text-[var(--pk-stamp)] uppercase">
                        Guru pembimbing
                    </p>
                </div>
            </div>
        </div>
    );
}

function Chip({
    children,
    tone,
}: {
    children: React.ReactNode;
    tone?: 'hadir';
}) {
    return (
        <span
            className={`rounded-full border px-3 py-1 font-mono text-[0.62rem] tracking-[0.1em] uppercase ${
                tone === 'hadir'
                    ? 'border-[var(--status-hadir)]/40 text-[var(--status-hadir)]'
                    : 'border-[var(--pk-rule)] text-[var(--pk-ink-soft)]'
            }`}
        >
            {children}
        </span>
    );
}
