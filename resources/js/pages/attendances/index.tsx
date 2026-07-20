import { Link } from '@inertiajs/react';
import { CircleCheck, CircleX, ClipboardList, Clock } from 'lucide-react';
import {
    AttendanceStatusBadge,
    VerificationBadge,
} from '@/components/attendance/attendance-badges';
import type {
    AttendanceStatus,
    VerificationStatus,
} from '@/components/attendance/attendance-badges';
import type { Column } from '@/components/data-table/data-table';
import { DataTableCard } from '@/components/data-table/data-table-card';
import { FilterSelect } from '@/components/data-table/filter-select';
import { StatCard } from '@/components/shared/stat-card';
import { Button } from '@/components/ui/button';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, SelectOption, TableState } from '@/types/table';

type Attendance = {
    id: string;
    date: string;
    status: AttendanceStatus;
    verification_status: VerificationStatus;
    check_in_at: string | null;
    check_out_at: string | null;
    placement: {
        id: string;
        student: { id: string; name: string; nis: string };
        company: { id: string; name: string };
    };
};

type Stats = {
    total: number;
    verified: number;
    pending: number;
    rejected: number;
};

type Props = {
    attendances: Paginated<Attendance>;
    table: TableState;
    statuses: SelectOption[];
    verificationStatuses: SelectOption[];
    canVerify: boolean;
    stats: Stats;
};

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
        new Date(iso),
    );
}

function formatTime(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', { timeStyle: 'short' }).format(
        new Date(iso),
    );
}

export default function AttendanceIndex({
    attendances,
    table,
    statuses,
    verificationStatuses,
    canVerify,
    stats,
}: Props) {
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/attendances', table);

    const columns: Column<Attendance>[] = [
        {
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {attendances.from !== null
                        ? attendances.from + index
                        : index + 1}
                </span>
            ),
        },
        {
            key: 'date',
            header: 'Tanggal',
            sortable: true,
            cell: (row) => (
                <Link
                    href={`/attendances/${row.id}`}
                    className="font-medium tabular-nums underline-offset-4 hover:underline"
                >
                    {formatDate(row.date)}
                </Link>
            ),
        },
        {
            key: 'student',
            header: 'Siswa',
            cell: (row) => (
                <div className="grid gap-0.5">
                    <span>{row.placement.student.name}</span>
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {row.placement.student.nis}
                    </span>
                </div>
            ),
        },
        {
            key: 'time',
            header: 'Masuk / Pulang',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-xs tabular-nums">
                    {formatTime(row.check_in_at)} –{' '}
                    {formatTime(row.check_out_at)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            sortable: true,
            cell: (row) => <AttendanceStatusBadge status={row.status} />,
        },
        {
            key: 'verification_status',
            header: 'Verifikasi',
            sortable: true,
            cell: (row) => (
                <VerificationBadge status={row.verification_status} />
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            className: 'w-24',
            cell: (row) => (
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/attendances/${row.id}`}>
                        {canVerify && row.verification_status === 'pending'
                            ? 'Verifikasi'
                            : 'Detail'}
                    </Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            title="Riwayat Absensi"
            description="Riwayat dan verifikasi absensi PKL siswa."
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={ClipboardList}
                        tone="blue"
                        label="Total Absensi"
                        value={stats.total}
                        hint="Semua catatan"
                    />
                    <StatCard
                        icon={Clock}
                        tone="amber"
                        label="Menunggu Verifikasi"
                        value={stats.pending}
                        hint="Perlu ditinjau"
                    />
                    <StatCard
                        icon={CircleCheck}
                        tone="green"
                        label="Terverifikasi"
                        value={stats.verified}
                        hint="Sudah disetujui"
                    />
                    <StatCard
                        icon={CircleX}
                        tone="violet"
                        label="Ditolak"
                        value={stats.rejected}
                        hint="Perlu perbaikan"
                    />
                </div>

                <DataTableCard
                    columns={columns}
                    page={attendances}
                    state={table}
                    onSort={sortBy}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                    rowKey={(row) => row.id}
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari nama siswa atau NIS..."
                    emptyMessage="Belum ada absensi."
                    filters={
                        <>
                            <FilterSelect
                                label="Filter status"
                                allLabel="Semua status"
                                value={table.filters.status ?? 'all'}
                                onValueChange={(value) =>
                                    filterBy('status', value)
                                }
                                options={statuses}
                            />
                            <FilterSelect
                                label="Filter verifikasi"
                                allLabel="Semua verifikasi"
                                value={
                                    table.filters.verification_status ?? 'all'
                                }
                                onValueChange={(value) =>
                                    filterBy('verification_status', value)
                                }
                                options={verificationStatuses}
                            />
                        </>
                    }
                />
            </div>
        </AppLayout>
    );
}
