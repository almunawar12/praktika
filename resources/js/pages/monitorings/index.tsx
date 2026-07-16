import { Link } from '@inertiajs/react';
import { MapPin, Paperclip, Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import { Button } from '@/components/ui/button';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, TableState } from '@/types/table';

type Monitoring = {
    id: string;
    visit_date: string;
    note: string;
    recommendation: string | null;
    latitude: string | null;
    attachments_count: number;
    teacher: { id: string; name: string };
    placement: {
        id: string;
        student: { id: string; name: string; nis: string };
        company: { id: string; name: string };
    };
};

type Props = {
    monitorings: Paginated<Monitoring>;
    table: TableState;
    canCreate: boolean;
};

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
        new Date(iso),
    );
}

export default function MonitoringIndex({
    monitorings,
    table,
    canCreate,
}: Props) {
    const { search, setSearch, sortBy, goToPage, setPerPage } = useTableQuery(
        '/monitorings',
        table,
    );

    const columns: Column<Monitoring>[] = [
        {
            key: 'visit_date',
            header: 'Tanggal',
            sortable: true,
            cell: (row) => (
                <Link
                    href={`/monitorings/${row.id}`}
                    className="font-medium tabular-nums underline-offset-4 hover:underline"
                >
                    {formatDate(row.visit_date)}
                </Link>
            ),
        },
        {
            key: 'student',
            header: 'Siswa',
            cell: (row) => (
                <div className="grid gap-0.5">
                    <span>{row.placement.student.name}</span>
                    <span className="text-xs text-muted-foreground">
                        {row.placement.company.name}
                    </span>
                </div>
            ),
        },
        {
            key: 'note',
            header: 'Catatan',
            hideOnMobile: true,
            cell: (row) => (
                <span className="line-clamp-2 max-w-md text-xs text-muted-foreground">
                    {row.note}
                </span>
            ),
        },
        {
            key: 'teacher',
            header: 'Guru',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-muted-foreground">
                    {row.teacher.name}
                </span>
            ),
        },
        {
            key: 'evidence',
            header: 'Bukti',
            cell: (row) => (
                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                    {row.latitude ? (
                        <span
                            className="flex items-center gap-0.5"
                            title="Lokasi terekam"
                        >
                            <MapPin className="size-3" aria-hidden="true" />
                        </span>
                    ) : null}
                    {row.attachments_count > 0 ? (
                        <span
                            className="flex items-center gap-0.5"
                            title={`${row.attachments_count} dokumentasi`}
                        >
                            <Paperclip className="size-3" aria-hidden="true" />
                            {row.attachments_count}
                        </span>
                    ) : null}
                    {!row.latitude && row.attachments_count === 0 ? '—' : null}
                </div>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/monitorings/${row.id}`}>Detail</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            title="Monitoring"
            description="Laporan kunjungan guru pembimbing"
        >
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari catatan, siswa, atau perusahaan..."
                >
                    {canCreate ? (
                        <Button asChild>
                            <Link href="/monitorings/create">
                                <Plus className="size-4" aria-hidden="true" />
                                Catat Kunjungan
                            </Link>
                        </Button>
                    ) : null}
                </DataTableToolbar>

                <DataTable
                    columns={columns}
                    page={monitorings}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada laporan kunjungan."
                />

                <DataTablePagination
                    page={monitorings}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
