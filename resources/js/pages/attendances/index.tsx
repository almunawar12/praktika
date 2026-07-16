import { Link } from '@inertiajs/react';
import {
    AttendanceStatusBadge,
    VerificationBadge,
} from '@/components/attendance/attendance-badges';
import type {
    AttendanceStatus,
    VerificationStatus,
} from '@/components/attendance/attendance-badges';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
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

type Props = {
    attendances: Paginated<Attendance>;
    table: TableState;
    statuses: SelectOption[];
    verificationStatuses: SelectOption[];
    canVerify: boolean;
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
}: Props) {
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/attendances', table);

    const columns: Column<Attendance>[] = [
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
            header: '',
            className: 'w-12',
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
            title="Absensi"
            description="Riwayat dan verifikasi absensi PKL"
        >
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari nama siswa atau NIS..."
                    filters={[
                        {
                            key: 'status',
                            label: 'Status',
                            value: table.filters.status ?? 'all',
                            options: statuses,
                        },
                        {
                            key: 'verification_status',
                            label: 'Verifikasi',
                            value: table.filters.verification_status ?? 'all',
                            options: verificationStatuses,
                        },
                    ]}
                    onFilterChange={filterBy}
                />

                <DataTable
                    columns={columns}
                    page={attendances}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada absensi."
                />

                <DataTablePagination
                    page={attendances}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
