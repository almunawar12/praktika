import { Link } from '@inertiajs/react';
import {
    CircleCheck,
    CircleX,
    Clock,
    NotebookPen,
    Paperclip,
    Plus,
} from 'lucide-react';
import type { Column } from '@/components/data-table/data-table';
import { DataTableCard } from '@/components/data-table/data-table-card';
import { FilterSelect } from '@/components/data-table/filter-select';
import { ReviewBadge } from '@/components/journal/review-badges';
import type { ReviewStatus } from '@/components/journal/review-badges';
import { StatCard } from '@/components/shared/stat-card';
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

type Stats = {
    total: number;
    pending: number;
    approved: number;
    rejected: number;
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
    stats: Stats;
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
    stats,
}: Props) {
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/journals', table);

    const queueOptions: SelectOption[] = [];

    if (can.reviewAsIndustry) {
        queueOptions.push({ value: 'industry', label: 'Perlu review saya' });
    }

    if (can.reviewAsTeacher) {
        queueOptions.push({ value: 'teacher', label: 'Perlu review saya' });
    }

    const columns: Column<Journal>[] = [
        {
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {journals.from !== null ? journals.from + index : index + 1}
                </span>
            ),
        },
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
            header: 'Aksi',
            className: 'w-20',
            cell: (row) => (
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/journals/${row.id}`}>Detail</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            title="Jurnal Harian"
            description="Jurnal harian kegiatan PKL siswa dan statusnya."
            actions={
                can.create ? (
                    <Button asChild>
                        <Link href="/journals/create">
                            <Plus className="size-4" aria-hidden="true" />
                            Isi Jurnal
                        </Link>
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={NotebookPen}
                        tone="blue"
                        label="Total Jurnal"
                        value={stats.total}
                        hint="Semua entri"
                    />
                    <StatCard
                        icon={Clock}
                        tone="amber"
                        label="Menunggu"
                        value={stats.pending}
                        hint="Belum direview"
                    />
                    <StatCard
                        icon={CircleCheck}
                        tone="green"
                        label="Disetujui"
                        value={stats.approved}
                        hint="Review selesai"
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
                    page={journals}
                    state={table}
                    onSort={sortBy}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                    rowKey={(row) => row.id}
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari kegiatan, nama siswa, atau NIS..."
                    emptyMessage="Belum ada jurnal."
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
                            {queueOptions.length > 0 ? (
                                <FilterSelect
                                    label="Filter antrian"
                                    allLabel="Semua antrian"
                                    value={table.filters.queue ?? 'all'}
                                    onValueChange={(value) =>
                                        filterBy('queue', value)
                                    }
                                    options={queueOptions}
                                />
                            ) : null}
                        </>
                    }
                />
            </div>
        </AppLayout>
    );
}
