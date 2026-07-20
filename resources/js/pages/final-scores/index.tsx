import { Link } from '@inertiajs/react';
import { Award, CircleCheck, Clock, Gauge } from 'lucide-react';
import { GradeBadge } from '@/components/assessment/grade-badge';
import type { Grade } from '@/components/assessment/grade-badge';
import type { Column } from '@/components/data-table/data-table';
import { DataTableCard } from '@/components/data-table/data-table-card';
import { FilterSelect } from '@/components/data-table/filter-select';
import { StatCard } from '@/components/shared/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, TableState } from '@/types/table';

type Score = {
    id: string;
    industry_score: string;
    teacher_score: string;
    final_score: string;
    grade: Grade;
    published_at: string | null;
    placement: {
        id: string;
        student: { id: string; name: string; nis: string };
        company: { id: string; name: string };
    };
};

type Stats = {
    total: number;
    published: number;
    draft: number;
    average: number | null;
};

type Props = {
    scores: Paginated<Score>;
    table: TableState;
    can: { publish: boolean; calculate: boolean };
    stats: Stats;
};

export default function FinalScoreIndex({ scores, table, stats }: Props) {
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/final-scores', table);

    const columns: Column<Score>[] = [
        {
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {scores.from !== null ? scores.from + index : index + 1}
                </span>
            ),
        },
        {
            key: 'student',
            header: 'Siswa',
            cell: (row) => (
                <Link
                    href={`/final-scores/${row.id}`}
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
            key: 'industry_score',
            header: 'Industri',
            hideOnMobile: true,
            cell: (row) => (
                <span className="tabular-nums">
                    {Number(row.industry_score).toFixed(2)}
                </span>
            ),
        },
        {
            key: 'teacher_score',
            header: 'Guru',
            hideOnMobile: true,
            cell: (row) => (
                <span className="tabular-nums">
                    {Number(row.teacher_score).toFixed(2)}
                </span>
            ),
        },
        {
            key: 'final_score',
            header: 'Nilai Akhir',
            sortable: true,
            cell: (row) => (
                <span className="font-semibold tabular-nums">
                    {Number(row.final_score).toFixed(2)}
                </span>
            ),
        },
        {
            key: 'grade',
            header: 'Grade',
            sortable: true,
            cell: (row) => <GradeBadge grade={row.grade} />,
        },
        {
            key: 'published_at',
            header: 'Publikasi',
            cell: (row) =>
                row.published_at ? (
                    <Badge className="border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400">
                        Dipublikasikan
                    </Badge>
                ) : (
                    <Badge variant="secondary">Belum</Badge>
                ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            className: 'w-20',
            cell: (row) => (
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/final-scores/${row.id}`}>Detail</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            title="Nilai Akhir"
            description="Hasil akhir PKL siswa dari gabungan nilai industri dan guru."
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={Award}
                        tone="blue"
                        label="Total Nilai"
                        value={stats.total}
                        hint="Semua nilai akhir"
                    />
                    <StatCard
                        icon={CircleCheck}
                        tone="green"
                        label="Dipublikasikan"
                        value={stats.published}
                        hint="Terlihat siswa"
                    />
                    <StatCard
                        icon={Clock}
                        tone="amber"
                        label="Belum Terbit"
                        value={stats.draft}
                        hint="Masih draft"
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
                        hint="Seluruh siswa"
                    />
                </div>

                <DataTableCard
                    columns={columns}
                    page={scores}
                    state={table}
                    onSort={sortBy}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                    rowKey={(row) => row.id}
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari nama siswa atau NIS..."
                    emptyMessage="Belum ada nilai akhir."
                    filters={
                        <>
                            <FilterSelect
                                label="Filter grade"
                                allLabel="Semua grade"
                                value={table.filters.grade ?? 'all'}
                                onValueChange={(value) =>
                                    filterBy('grade', value)
                                }
                                options={['A', 'B', 'C', 'D', 'E'].map((g) => ({
                                    value: g,
                                    label: g,
                                }))}
                            />
                            <FilterSelect
                                label="Filter publikasi"
                                allLabel="Semua publikasi"
                                value={table.filters.state ?? 'all'}
                                onValueChange={(value) =>
                                    filterBy('state', value)
                                }
                                options={[
                                    {
                                        value: 'published',
                                        label: 'Dipublikasikan',
                                    },
                                    { value: 'draft', label: 'Belum' },
                                ]}
                            />
                        </>
                    }
                />
            </div>
        </AppLayout>
    );
}
