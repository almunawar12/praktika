import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import { RowActions } from '@/components/shared/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, SelectOption, TableState } from '@/types/table';

type SchoolClass = {
    id: string;
    name: string;
    students_count: number;
    department: { id: string; code: string; name: string };
};

type Props = {
    classes: Paginated<SchoolClass>;
    table: TableState;
    departments: SelectOption[];
};

export default function ClassIndex({ classes, table, departments }: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/classes', table);

    const columns: Column<SchoolClass>[] = [
        {
            key: 'name',
            header: 'Nama Kelas',
            sortable: true,
            cell: (row) => (
                <Link
                    href={`/master/classes/${row.id}`}
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {row.name}
                </Link>
            ),
        },
        {
            key: 'department',
            header: 'Jurusan',
            cell: (row) => (
                <Badge variant="outline">
                    {row.department.code} — {row.department.name}
                </Badge>
            ),
        },
        {
            key: 'students',
            header: 'Jumlah Siswa',
            hideOnMobile: true,
            cell: (row) => (
                <span className="tabular-nums">{row.students_count}</span>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
                <RowActions
                    showHref={`/master/classes/${row.id}`}
                    editHref={`/master/classes/${row.id}/edit`}
                    deleteHref={`/master/classes/${row.id}`}
                    deleteDescription={`Kelas "${row.name}" akan dihapus. Kelas yang masih memiliki siswa tidak dapat dihapus.`}
                    canUpdate={can('school-classes.update')}
                    canDelete={can('school-classes.delete')}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Kelas" description="Kelola daftar kelas per jurusan">
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari nama kelas atau jurusan..."
                    filters={[
                        {
                            key: 'department_id',
                            label: 'Jurusan',
                            value: table.filters.department_id ?? 'all',
                            options: departments,
                        },
                    ]}
                    onFilterChange={filterBy}
                >
                    {can('school-classes.create') ? (
                        <Button asChild>
                            <Link href="/master/classes/create">
                                <Plus className="size-4" aria-hidden="true" />
                                Tambah Kelas
                            </Link>
                        </Button>
                    ) : null}
                </DataTableToolbar>

                <DataTable
                    columns={columns}
                    page={classes}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada kelas."
                />

                <DataTablePagination
                    page={classes}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
