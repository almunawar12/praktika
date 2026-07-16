import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import { PlacementStatusBadge } from '@/components/pkl/placement-status-badge';
import type { PlacementStatus } from '@/components/pkl/placement-status-badge';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, SelectOption, TableState } from '@/types/table';

type Placement = {
    id: string;
    status: PlacementStatus;
    start_date: string;
    end_date: string;
    student: {
        id: string;
        name: string;
        nis: string;
        school_class: {
            id: string;
            name: string;
            department: { code: string };
        };
    };
    company: { id: string; name: string };
    teacher: { id: string; name: string };
};

type Props = {
    placements: Paginated<Placement>;
    table: TableState;
    statuses: SelectOption[];
    companies: SelectOption[];
    teachers: SelectOption[];
};

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
        new Date(iso),
    );
}

export default function PlacementIndex({
    placements,
    table,
    statuses,
    companies,
    teachers,
}: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/pkl/placements', table);

    const columns: Column<Placement>[] = [
        {
            key: 'student',
            header: 'Siswa',
            cell: (row) => (
                <Link
                    href={`/pkl/placements/${row.id}`}
                    className="grid gap-0.5 underline-offset-4 hover:underline"
                >
                    <span className="font-medium">{row.student.name}</span>
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {row.student.nis} · {row.student.school_class.name}
                    </span>
                </Link>
            ),
        },
        {
            key: 'company',
            header: 'Perusahaan',
            cell: (row) => row.company.name,
        },
        {
            key: 'teacher',
            header: 'Guru Pembimbing',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-muted-foreground">
                    {row.teacher.name}
                </span>
            ),
        },
        {
            key: 'start_date',
            header: 'Periode',
            sortable: true,
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-xs tabular-nums">
                    {formatDate(row.start_date)} – {formatDate(row.end_date)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            sortable: true,
            cell: (row) => <PlacementStatusBadge status={row.status} />,
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/pkl/placements/${row.id}`}>Detail</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            title="Penempatan PKL"
            description="Kelola penempatan siswa ke perusahaan"
        >
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari siswa, NIS, perusahaan, atau guru..."
                    filters={[
                        {
                            key: 'status',
                            label: 'Status',
                            value: table.filters.status ?? 'all',
                            options: statuses,
                        },
                        {
                            key: 'company_id',
                            label: 'Perusahaan',
                            value: table.filters.company_id ?? 'all',
                            options: companies,
                        },
                        {
                            key: 'teacher_id',
                            label: 'Guru',
                            value: table.filters.teacher_id ?? 'all',
                            options: teachers,
                        },
                    ]}
                    onFilterChange={filterBy}
                >
                    {can('placements.create') ? (
                        <Button asChild>
                            <Link href="/pkl/placements/create">
                                <Plus className="size-4" aria-hidden="true" />
                                Tambah Penempatan
                            </Link>
                        </Button>
                    ) : null}
                </DataTableToolbar>

                <DataTable
                    columns={columns}
                    page={placements}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada penempatan."
                />

                <DataTablePagination
                    page={placements}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
