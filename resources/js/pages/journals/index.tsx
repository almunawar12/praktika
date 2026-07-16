import { Link } from '@inertiajs/react';
import { Paperclip, Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import { ReviewBadge } from '@/components/journal/review-badges';
import type { ReviewStatus } from '@/components/journal/review-badges';
import { Button } from '@/components/ui/button';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, SelectOption, TableState } from '@/types/table';

type Journal = {
    id: string;
    date: string;
    activity: string;
    status: ReviewStatus;
    industry_status: ReviewStatus;
    teacher_status: ReviewStatus;
    attachments_count: number;
    placement: {
        id: string;
        student: { id: string; name: string; nis: string };
        company: { id: string; name: string };
    };
};

type Props = {
    journals: Paginated<Journal>;
    table: TableState;
    statuses: SelectOption[];
    can: {
        create: boolean;
        reviewAsIndustry: boolean;
        reviewAsTeacher: boolean;
    };
};

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
        new Date(iso),
    );
}

export default function JournalIndex({
    journals,
    table,
    statuses,
    can,
}: Props) {
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/journals', table);

    // "Needs my review" is the query a reviewer actually wants; which queue depends
    // on which stage they own.
    const queueOptions: SelectOption[] = [];

    if (can.reviewAsIndustry) {
        queueOptions.push({ value: 'industry', label: 'Perlu review saya' });
    }

    if (can.reviewAsTeacher) {
        queueOptions.push({ value: 'teacher', label: 'Perlu review saya' });
    }

    const columns: Column<Journal>[] = [
        {
            key: 'date',
            header: 'Tanggal',
            sortable: true,
            cell: (row) => (
                <Link
                    href={`/journals/${row.id}`}
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
            key: 'activity',
            header: 'Kegiatan',
            hideOnMobile: true,
            cell: (row) => (
                <span className="line-clamp-2 max-w-md text-xs text-muted-foreground">
                    {row.activity}
                </span>
            ),
        },
        {
            key: 'review',
            header: 'Review',
            cell: (row) => (
                <div className="flex flex-wrap gap-1">
                    <ReviewBadge
                        status={row.industry_status}
                        prefix="Industri"
                    />
                    <ReviewBadge status={row.teacher_status} prefix="Guru" />
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            sortable: true,
            cell: (row) => (
                <div className="flex items-center gap-1.5">
                    <ReviewBadge status={row.status} />
                    {row.attachments_count > 0 ? (
                        <span
                            className="flex items-center gap-0.5 text-xs text-muted-foreground"
                            title={`${row.attachments_count} dokumentasi`}
                        >
                            <Paperclip className="size-3" aria-hidden="true" />
                            {row.attachments_count}
                        </span>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/journals/${row.id}`}>Detail</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="Jurnal" description="Jurnal harian kegiatan PKL">
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari kegiatan, nama siswa, atau NIS..."
                    filters={[
                        {
                            key: 'status',
                            label: 'Status',
                            value: table.filters.status ?? 'all',
                            options: statuses,
                        },
                        ...(queueOptions.length > 0
                            ? [
                                  {
                                      key: 'queue',
                                      label: 'Antrian',
                                      value: table.filters.queue ?? 'all',
                                      options: queueOptions,
                                  },
                              ]
                            : []),
                    ]}
                    onFilterChange={filterBy}
                >
                    {can.create ? (
                        <Button asChild>
                            <Link href="/journals/create">
                                <Plus className="size-4" aria-hidden="true" />
                                Isi Jurnal
                            </Link>
                        </Button>
                    ) : null}
                </DataTableToolbar>

                <DataTable
                    columns={columns}
                    page={journals}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada jurnal."
                />

                <DataTablePagination
                    page={journals}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
