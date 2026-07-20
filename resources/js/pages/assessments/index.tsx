import { Link, router } from '@inertiajs/react';
import { ClipboardList, FilePen, Gauge, Lock, LockOpen } from 'lucide-react';
import type { Column } from '@/components/data-table/data-table';
import { DataTableCard } from '@/components/data-table/data-table-card';
import { FilterSelect } from '@/components/data-table/filter-select';
import { StatCard } from '@/components/shared/stat-card';
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

type Stats = {
    total: number;
    submitted: number;
    draft: number;
    average: number | null;
};

type Props = {
    assessments: Paginated<Assessment>;
    table: TableState;
    types: SelectOption[];
    stats: Stats;
};

const TYPE_LABELS: Record<Assessment['type'], string> = {
    industry: 'Industri',
    teacher: 'Guru',
};

export default function AssessmentIndex({
    assessments,
    table,
    types,
    stats,
}: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/assessments', table);

    const canUnlock = can('assessments.viewAny') && can('final-scores.publish');

    const columns: Column<Assessment>[] = [
        {
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {assessments.from !== null
                        ? assessments.from + index
                        : index + 1}
                </span>
            ),
        },
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
            header: 'Aksi',
            className: 'w-20',
            cell: (row) =>
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
            description="Penilaian industri dan guru pembimbing untuk setiap siswa PKL."
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={ClipboardList}
                        tone="blue"
                        label="Total Penilaian"
                        value={stats.total}
                        hint="Semua penilaian"
                    />
                    <StatCard
                        icon={Lock}
                        tone="green"
                        label="Terkirim"
                        value={stats.submitted}
                        hint="Sudah dikunci"
                    />
                    <StatCard
                        icon={FilePen}
                        tone="amber"
                        label="Draft"
                        value={stats.draft}
                        hint="Belum dikirim"
                    />
                    <StatCard
                        icon={Gauge}
                        tone="violet"
                        label="Rata-rata Nilai"
                        value={
                            stats.average === null
                                ? '—'
                                : stats.average.toFixed(2)
                        }
                        hint="Dari yang terkirim"
                    />
                </div>

                <DataTableCard
                    columns={columns}
                    page={assessments}
                    state={table}
                    onSort={sortBy}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                    rowKey={(row) => row.id}
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari nama siswa atau NIS..."
                    emptyMessage="Belum ada penilaian."
                    filters={
                        <>
                            <FilterSelect
                                label="Filter jenis"
                                allLabel="Semua jenis"
                                value={table.filters.type ?? 'all'}
                                onValueChange={(value) =>
                                    filterBy('type', value)
                                }
                                options={types}
                            />
                            <FilterSelect
                                label="Filter status"
                                allLabel="Semua status"
                                value={table.filters.state ?? 'all'}
                                onValueChange={(value) =>
                                    filterBy('state', value)
                                }
                                options={[
                                    { value: 'draft', label: 'Draft' },
                                    { value: 'submitted', label: 'Terkirim' },
                                ]}
                            />
                        </>
                    }
                />
            </div>
        </AppLayout>
    );
}
