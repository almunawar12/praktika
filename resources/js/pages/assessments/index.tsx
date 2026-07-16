import { Link, router } from '@inertiajs/react';
import { Lock, LockOpen } from 'lucide-react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, SelectOption, TableState } from '@/types/table';

type Assessment = {
    id: string;
    type: 'industry' | 'teacher';
    average_score: string | null;
    submitted_at: string | null;
    assessor: { id: string; name: string };
    placement: {
        id: string;
        student: { id: string; name: string; nis: string };
        company: { id: string; name: string };
    };
};

type Props = {
    assessments: Paginated<Assessment>;
    table: TableState;
    types: SelectOption[];
};

const TYPE_LABELS: Record<Assessment['type'], string> = {
    industry: 'Industri',
    teacher: 'Guru',
};

export default function AssessmentIndex({ assessments, table, types }: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/assessments', table);

    const canUnlock = can('assessments.viewAny') && can('final-scores.publish');

    const columns: Column<Assessment>[] = [
        {
            key: 'student',
            header: 'Siswa',
            cell: (row) => (
                <Link
                    href={`/assessments/${row.placement.id}/${row.type}`}
                    className="grid gap-0.5 underline-offset-4 hover:underline"
                >
                    <span className="font-medium">
                        {row.placement.student.name}
                    </span>
                    <span className="text-xs text-muted-foreground">
                        {row.placement.company.name}
                    </span>
                </Link>
            ),
        },
        {
            key: 'type',
            header: 'Jenis',
            sortable: true,
            cell: (row) => (
                <Badge variant="outline">{TYPE_LABELS[row.type]}</Badge>
            ),
        },
        {
            key: 'average_score',
            header: 'Rata-rata',
            sortable: true,
            cell: (row) => (
                <span className="tabular-nums">
                    {row.average_score
                        ? Number(row.average_score).toFixed(2)
                        : '—'}
                </span>
            ),
        },
        {
            key: 'assessor',
            header: 'Penilai',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-muted-foreground">
                    {row.assessor.name}
                </span>
            ),
        },
        {
            key: 'submitted_at',
            header: 'Status',
            sortable: true,
            cell: (row) =>
                row.submitted_at ? (
                    <Badge className="gap-1 border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400">
                        <Lock className="size-3" aria-hidden="true" />
                        Terkirim
                    </Badge>
                ) : (
                    <Badge variant="secondary">Draft</Badge>
                ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) =>
                // Reopening changes the evidence a final score rests on, so it is an
                // admin decision — see AssessmentPolicy::unlock.
                canUnlock && row.submitted_at ? (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            router.put(
                                `/assessments/${row.id}/unlock`,
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <LockOpen className="size-3.5" aria-hidden="true" />
                        Buka
                    </Button>
                ) : (
                    <Button variant="ghost" size="sm" asChild>
                        <Link
                            href={`/assessments/${row.placement.id}/${row.type}`}
                        >
                            Buka
                        </Link>
                    </Button>
                ),
        },
    ];

    return (
        <AppLayout
            title="Penilaian"
            description="Penilaian industri dan guru pembimbing"
        >
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari nama siswa atau NIS..."
                    filters={[
                        {
                            key: 'type',
                            label: 'Jenis',
                            value: table.filters.type ?? 'all',
                            options: types,
                        },
                        {
                            key: 'state',
                            label: 'Status',
                            value: table.filters.state ?? 'all',
                            options: [
                                { value: 'draft', label: 'Draft' },
                                { value: 'submitted', label: 'Terkirim' },
                            ],
                        },
                    ]}
                    onFilterChange={filterBy}
                />

                <DataTable
                    columns={columns}
                    page={assessments}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada penilaian."
                />

                <DataTablePagination
                    page={assessments}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
