import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { PublicLayout } from '@/components/public/public-layout';

type Task = {
    title: string;
    intro?: string;
    steps: string[];
    note?: string;
};

type Section = {
    id: string;
    label: string;
    title: string;
    lead: string;
    tasks: Task[];
};

const sections: Section[] = [
    {
        id: 'mulai',
        label: 'Sebelum mulai',
        title: 'Sebelum mulai',
        lead: 'Praktika tidak punya pendaftaran publik. Admin PKL yang membuat akun Anda, lalu mengirimkan email dan kata sandi awal.',
        tasks: [
            {
                title: 'Masuk pertama kali',
                steps: [
                    'Buka halaman Masuk dari tombol di kanan atas.',
                    'Isi email dan kata sandi yang diberikan Admin PKL.',
                    'Tekan Masuk. Anda langsung diarahkan ke dasbor sesuai peran.',
                ],
                note: 'Kata sandi salah tiga kali berturut-turut akan menahan percobaan berikutnya beberapa saat. Tunggu, lalu coba lagi.',
            },
            {
                title: 'Lupa kata sandi',
                steps: [
                    'Di halaman Masuk, pilih Lupa kata sandi.',
                    'Masukkan email akun Anda, lalu tekan kirim.',
                    'Buka tautan pada email yang masuk dan tetapkan kata sandi baru.',
                ],
                note: 'Tautan berlaku terbatas. Jika kedaluwarsa, ulangi permintaan dari awal.',
            },
            {
                title: 'Membuka Praktika di ponsel',
                intro: 'Absensi dan jurnal dirancang untuk dipakai dari ponsel di tempat PKL.',
                steps: [
                    'Buka alamat Praktika lewat peramban ponsel.',
                    'Izinkan akses lokasi dan kamera ketika diminta — keduanya dipakai saat absen.',
                    'Tambahkan ke layar utama agar bisa dibuka seperti aplikasi.',
                ],
            },
        ],
    },
    {
        id: 'siswa',
        label: 'Siswa',
        title: 'Panduan siswa',
        lead: 'Dua hal yang Anda kerjakan setiap hari kerja: absen dan jurnal. Sisanya tinggal memantau.',
        tasks: [
            {
                title: 'Absen masuk dan pulang',
                steps: [
                    'Buka menu Absensi dari dasbor.',
                    'Tekan Check In. Praktika meminta lokasi dan membuka kamera.',
                    'Ambil foto di tempat PKL, lalu kirim. Waktu, titik lokasi, dan foto tersimpan bersamaan.',
                    'Saat pulang, buka menu yang sama dan tekan Check Out.',
                ],
                note: 'Izin lokasi ditolak berarti absen tidak bisa dikirim. Aktifkan kembali lewat pengaturan situs di peramban.',
            },
            {
                title: 'Menulis jurnal harian',
                steps: [
                    'Buka menu Jurnal, lalu tekan Tambah jurnal.',
                    'Pilih tanggal kegiatan dan tulis apa yang Anda kerjakan hari itu.',
                    'Lampirkan foto pendukung bila ada, lalu simpan.',
                    'Jurnal masuk dengan status Menunggu sampai guru pembimbing memeriksanya.',
                ],
            },
            {
                title: 'Jurnal ditolak, lalu apa',
                steps: [
                    'Buka jurnal berstatus Ditolak dan baca catatan dari guru.',
                    'Perbaiki isi kegiatan sesuai catatan tersebut.',
                    'Simpan ulang. Statusnya kembali menjadi Menunggu.',
                ],
            },
            {
                title: 'Melihat nilai',
                steps: [
                    'Buka menu Penilaian.',
                    'Nilai dari pembimbing industri dan guru tampil terpisah.',
                    'Nilai akhir muncul setelah kedua nilai lengkap.',
                ],
                note: 'Anda hanya melihat data diri sendiri. Data siswa lain tidak dapat diakses.',
            },
        ],
    },
    {
        id: 'guru',
        label: 'Guru Pembimbing',
        title: 'Panduan guru pembimbing',
        lead: 'Anda memegang siswa bimbingan: memvalidasi jurnal mereka, mencatat kunjungan, dan memberi nilai di akhir periode.',
        tasks: [
            {
                title: 'Memvalidasi jurnal',
                steps: [
                    'Buka menu Jurnal. Daftar tersaring ke siswa bimbingan Anda.',
                    'Pilih jurnal berstatus Menunggu dan baca isinya.',
                    'Tekan Setujui bila sesuai, atau Tolak dan tulis catatan perbaikan.',
                ],
                note: 'Catatan penolakan wajib diisi — itu yang dibaca siswa untuk memperbaiki jurnalnya.',
            },
            {
                title: 'Mencatat kunjungan monitoring',
                steps: [
                    'Buka menu Monitoring, lalu tekan Tambah monitoring.',
                    'Pilih siswa dan penempatan yang dikunjungi.',
                    'Praktika merekam waktu kunjungan; ambil foto di lokasi lewat kamera.',
                    'Tulis temuan atau catatan pembinaan, lalu simpan.',
                ],
            },
            {
                title: 'Memberi nilai guru',
                steps: [
                    'Buka menu Penilaian dan pilih siswa bimbingan.',
                    'Isi komponen nilai guru sesuai rubrik sekolah.',
                    'Simpan. Nilai akhir dihitung sistem setelah nilai industri masuk.',
                ],
            },
            {
                title: 'Memantau kehadiran',
                steps: [
                    'Buka dasbor untuk ringkasan kehadiran siswa bimbingan.',
                    'Masuk ke menu Absensi untuk rincian per tanggal.',
                    'Gunakan penyaring status untuk menemukan alpha yang perlu ditindaklanjuti.',
                ],
            },
        ],
    },
    {
        id: 'industri',
        label: 'Pembimbing Industri',
        title: 'Panduan pembimbing industri',
        lead: 'Anda menilai siswa yang bekerja di perusahaan Anda. Aksesnya terbatas pada siswa tersebut.',
        tasks: [
            {
                title: 'Memberi nilai industri',
                steps: [
                    'Buka menu Penilaian setelah masuk.',
                    'Pilih siswa yang ditempatkan di perusahaan Anda.',
                    'Isi komponen nilai industri, lalu simpan.',
                ],
                note: 'Nilai dapat diperbarui selama periode PKL masih berjalan.',
            },
            {
                title: 'Memeriksa kehadiran dan jurnal siswa',
                steps: [
                    'Buka menu Absensi untuk melihat rekap kehadiran siswa Anda.',
                    'Buka menu Jurnal untuk membaca kegiatan harian yang mereka catat.',
                ],
            },
        ],
    },
    {
        id: 'admin',
        label: 'Admin PKL',
        title: 'Panduan admin PKL',
        lead: 'Anda menyiapkan seluruh data yang dipakai peran lain. Urutannya penting: data master dulu, penempatan belakangan.',
        tasks: [
            {
                title: 'Menyiapkan data master',
                intro: 'Kerjakan berurutan agar setiap pilihan tersedia saat dibutuhkan.',
                steps: [
                    'Buat tahun ajaran, lalu tandai yang aktif.',
                    'Isi jurusan dan kelas sekolah.',
                    'Tambahkan data guru pembimbing dan siswa.',
                    'Daftarkan perusahaan DU/DI beserta pembimbing industrinya.',
                ],
            },
            {
                title: 'Membuat penempatan',
                steps: [
                    'Buka menu Penempatan, lalu tekan Tambah penempatan.',
                    'Pilih siswa, perusahaan, dan guru pembimbing.',
                    'Tetapkan tanggal mulai dan tanggal selesai periode PKL.',
                    'Simpan. Siswa langsung bisa absen dan mengisi jurnal.',
                ],
                note: 'Satu siswa hanya boleh punya satu penempatan aktif pada tahun ajaran yang sama. Penempatan menutup sendiri ketika periodenya berakhir.',
            },
            {
                title: 'Membuat akun pengguna',
                steps: [
                    'Buka menu Pengguna di bagian sistem.',
                    'Tambahkan pengguna dan pilih perannya.',
                    'Serahkan email dan kata sandi awal kepada yang bersangkutan.',
                ],
                note: 'Peran menentukan seluruh hak akses. Beri peran serendah yang cukup untuk tugasnya.',
            },
            {
                title: 'Menarik laporan',
                steps: [
                    'Buka menu Laporan.',
                    'Pilih jenis rekap: kehadiran, jurnal, atau nilai.',
                    'Saring per kelas, per perusahaan, atau per rentang tanggal.',
                    'Cetak atau unduh hasilnya untuk arsip sekolah.',
                ],
            },
        ],
    },
    {
        id: 'sistem',
        label: 'Super Admin',
        title: 'Panduan super admin',
        lead: 'Di atas Admin PKL, Anda mengatur siapa boleh melakukan apa dan menelusuri jejak perubahan data.',
        tasks: [
            {
                title: 'Mengatur peran dan izin',
                steps: [
                    'Buka menu Peran di bagian sistem.',
                    'Pilih peran, lalu centang izin yang diberikan.',
                    'Simpan. Perubahan berlaku pada sesi berikutnya pengguna tersebut.',
                ],
            },
            {
                title: 'Menelusuri log aktivitas',
                steps: [
                    'Buka menu Log aktivitas.',
                    'Saring berdasarkan pengguna, jenis data, atau tanggal.',
                    'Buka satu entri untuk melihat nilai sebelum dan sesudah perubahan.',
                ],
            },
        ],
    },
    {
        id: 'masalah',
        label: 'Masalah umum',
        title: 'Masalah umum',
        lead: 'Kendala yang paling sering muncul di lapangan, dan cara menyelesaikannya sendiri.',
        tasks: [
            {
                title: 'Tombol Check In tidak bisa ditekan',
                steps: [
                    'Pastikan Anda punya penempatan aktif — tanyakan ke Admin PKL bila belum.',
                    'Periksa apakah Anda sudah check in hari ini.',
                    'Aktifkan izin lokasi untuk situs Praktika di peramban.',
                ],
            },
            {
                title: 'Kamera tidak terbuka saat absen',
                steps: [
                    'Buka pengaturan situs di peramban dan izinkan akses kamera.',
                    'Tutup aplikasi lain yang sedang memakai kamera.',
                    'Muat ulang halaman, lalu ulangi absen.',
                ],
            },
            {
                title: 'Menu yang dicari tidak ada',
                steps: [
                    'Menu mengikuti peran akun. Peran yang berbeda melihat menu berbeda.',
                    'Bila menu yang Anda butuhkan tidak muncul, minta Admin PKL memeriksa peran akun Anda.',
                ],
            },
            {
                title: 'Nilai akhir belum muncul',
                steps: [
                    'Nilai akhir dihitung setelah nilai industri dan nilai guru sama-sama terisi.',
                    'Periksa komponen mana yang masih kosong di menu Penilaian.',
                    'Hubungi pembimbing yang bersangkutan untuk melengkapinya.',
                ],
            },
        ],
    },
];

const sectionIds = sections.map((section) => section.id);

export default function Panduan() {
    const activeId = useActiveSection(sectionIds);

    return (
        <PublicLayout active="panduan">
            <Head title="Panduan" />

            <section className="pk-grid relative overflow-hidden border-b border-[var(--pk-rule)]">
                <div className="absolute inset-0 bg-gradient-to-b from-[var(--pk-paper)]/40 to-[var(--pk-paper)]" />
                <div className="relative mx-auto w-full max-w-6xl px-5 py-14 sm:px-8 sm:py-20">
                    <p className="font-mono text-[0.7rem] tracking-[0.22em] text-[var(--pk-ink-soft)] uppercase">
                        Panduan penggunaan
                    </p>
                    <h1 className="mt-5 max-w-3xl font-display text-[clamp(2.2rem,6vw,3.8rem)] leading-[0.98] font-extrabold tracking-[-0.035em]">
                        Cara memakai Praktika, per peran
                    </h1>
                    <p className="mt-5 max-w-xl text-lg leading-relaxed text-[var(--pk-ink-soft)]">
                        Cari peran Anda, ikuti langkahnya. Setiap langkah
                        ditulis seperti yang terlihat di layar.
                    </p>
                </div>
            </section>

            <div className="mx-auto grid w-full max-w-6xl gap-12 px-5 py-14 sm:px-8 lg:grid-cols-[minmax(0,14rem)_1fr] lg:gap-16">
                <nav
                    aria-label="Daftar isi panduan"
                    className="min-w-0 lg:sticky lg:top-24 lg:self-start"
                >
                    <p className="font-mono text-[0.66rem] tracking-[0.18em] text-[var(--pk-ink-soft)] uppercase">
                        Daftar isi
                    </p>
                    <ul className="mt-4 flex gap-2 overflow-x-auto pb-2 lg:flex-col lg:gap-0 lg:overflow-visible lg:border-l lg:border-[var(--pk-rule)] lg:pb-0">
                        {sections.map((section) => {
                            const isActive = activeId === section.id;

                            return (
                                <li key={section.id}>
                                    <a
                                        href={`#${section.id}`}
                                        aria-current={
                                            isActive ? 'true' : undefined
                                        }
                                        className={`block shrink-0 rounded-full border px-4 py-2 text-sm whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--pk-pen)] lg:-ml-px lg:rounded-none lg:border-0 lg:border-l-2 lg:px-4 lg:py-2.5 ${
                                            isActive
                                                ? 'border-[var(--pk-ink)] font-medium text-[var(--pk-ink)] lg:border-l-[var(--pk-pen)]'
                                                : 'border-[var(--pk-rule)] text-[var(--pk-ink-soft)] hover:text-[var(--pk-ink)] lg:border-l-transparent'
                                        }`}
                                    >
                                        {section.label}
                                    </a>
                                </li>
                            );
                        })}
                    </ul>

                    <Link
                        href="/login"
                        className="mt-6 hidden rounded-full bg-[var(--pk-pen)] px-5 py-2.5 text-center text-sm font-semibold text-[var(--pk-pen-fg)] transition-transform hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pk-pen)] lg:block"
                    >
                        Masuk ke Praktika
                    </Link>
                </nav>

                <div className="min-w-0">
                    {sections.map((section) => (
                        <section
                            key={section.id}
                            id={section.id}
                            className="scroll-mt-24 border-t border-[var(--pk-rule)] pt-10 pb-14 first:border-t-0 first:pt-0"
                        >
                            <h2 className="font-display text-[clamp(1.6rem,3.5vw,2.2rem)] leading-tight font-extrabold tracking-[-0.03em]">
                                {section.title}
                            </h2>
                            <p className="mt-3 max-w-2xl leading-relaxed text-[var(--pk-ink-soft)]">
                                {section.lead}
                            </p>

                            <div className="mt-8 space-y-5">
                                {section.tasks.map((task) => (
                                    <TaskCard key={task.title} task={task} />
                                ))}
                            </div>
                        </section>
                    ))}

                    <div className="rounded-2xl border border-[var(--pk-rule)] bg-[var(--pk-sheet)] p-7">
                        <h2 className="font-display text-xl font-bold tracking-tight">
                            Masih tersangkut?
                        </h2>
                        <p className="mt-2 max-w-lg text-[0.95rem] leading-relaxed text-[var(--pk-ink-soft)]">
                            Hubungi Admin PKL sekolah Anda. Mereka bisa
                            memeriksa peran akun, memperbaiki penempatan, dan
                            mengatur ulang kata sandi.
                        </p>
                        <Link
                            href="/login"
                            className="mt-5 inline-block rounded-full bg-[var(--pk-ink)] px-6 py-3 text-sm font-semibold text-[var(--pk-sheet)] transition-transform hover:-translate-y-0.5 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[var(--pk-pen)]"
                        >
                            Masuk ke Praktika
                        </Link>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}

function TaskCard({ task }: { task: Task }) {
    return (
        <article className="rounded-2xl border border-[var(--pk-rule)] bg-[var(--pk-sheet)] p-6 sm:p-7">
            <h3 className="font-display text-lg font-bold tracking-tight">
                {task.title}
            </h3>

            {task.intro ? (
                <p className="mt-2 text-[0.92rem] leading-relaxed text-[var(--pk-ink-soft)]">
                    {task.intro}
                </p>
            ) : null}

            {/* Steps are a real sequence, so they are numbered. */}
            <ol className="mt-4 space-y-3">
                {task.steps.map((step, index) => (
                    <li key={step} className="flex gap-4">
                        <span className="mt-0.5 font-mono text-[0.7rem] text-[var(--pk-ink-soft)] tabular-nums">
                            {String(index + 1).padStart(2, '0')}
                        </span>
                        <span className="text-[0.95rem] leading-relaxed">
                            {step}
                        </span>
                    </li>
                ))}
            </ol>

            {task.note ? (
                <p className="mt-5 border-l-2 border-[var(--pk-stamp)] pl-4 text-[0.88rem] leading-relaxed text-[var(--pk-ink-soft)]">
                    {task.note}
                </p>
            ) : null}
        </article>
    );
}

/** Highlights the table-of-contents entry for the section nearest the top. */
function useActiveSection(ids: string[]) {
    const [activeId, setActiveId] = useState(ids[0]);

    useEffect(() => {
        const elements = ids
            .map((id) => document.getElementById(id))
            .filter((element): element is HTMLElement => element !== null);

        if (elements.length === 0) {
            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                const visible = entries
                    .filter((entry) => entry.isIntersecting)
                    .sort(
                        (a, b) =>
                            a.boundingClientRect.top - b.boundingClientRect.top,
                    );

                if (visible[0]) {
                    setActiveId(visible[0].target.id);
                }
            },
            { rootMargin: '-20% 0px -70% 0px' },
        );

        elements.forEach((element) => observer.observe(element));

        return () => observer.disconnect();
    }, [ids]);

    return activeId;
}
