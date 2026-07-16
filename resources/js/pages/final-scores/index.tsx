import { Link } from '@inertiajs/react';
import { GradeBadge } from '@/components/assessment/grade-badge';
import type { Grade } from '@/components/assessment/grade-badge';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
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

type Props = {
    scores: Paginated<Score>;
    table: TableState;
    can: { publish: boolean; calculate: boolean };
};

export default function FinalScoreIndex({ scores, table }: Props) {
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/final-scores', table);

    const columns: Column<Score>[] = [
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
            header: '',
            className: 'w-12',
            cell: (row) => (
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/final-scores/${row.id}`}>Detail</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="Nilai Akhir" description="Hasil akhir PKL">
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari nama siswa atau NIS..."
                    filters={[
                        {
                            key: 'grade',
                            label: 'Grade',
                            value: table.filters.grade ?? 'all',
                            options: ['A', 'B', 'C', 'D', 'E'].map((g) => ({
                                value: g,
                                label: g,
                            })),
                        },
                        {
                            key: 'state',
                            label: 'Publikasi',
                            value: table.filters.state ?? 'all',
                            options: [
                                { value: 'published', label: 'Dipublikasikan' },
                                { value: 'draft', label: 'Belum' },
                            ],
                        },
                    ]}
                    onFilterChange={filterBy}
                />

                <DataTable
                    columns={columns}
                    page={scores}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada nilai akhir."
                />

                <DataTablePagination
                    page={scores}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
