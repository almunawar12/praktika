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
import type { Paginated, TableState } from '@/types/table';

type Department = {
    id: string;
    code: string;
    name: string;
    school_classes_count: number;
};

type Props = {
    departments: Paginated<Department>;
    table: TableState;
};

export default function DepartmentIndex({ departments, table }: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, goToPage, setPerPage } = useTableQuery(
        '/master/departments',
        table,
    );

    const columns: Column<Department>[] = [
        {
            key: 'code',
            header: 'Kode',
            sortable: true,
            cell: (row) => <Badge variant="outline">{row.code}</Badge>,
        },
        {
            key: 'name',
            header: 'Nama Jurusan',
            sortable: true,
            cell: (row) => (
                <Link
                    href={`/master/departments/${row.id}`}
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {row.name}
                </Link>
            ),
        },
        {
            key: 'classes',
            header: 'Jumlah Kelas',
            hideOnMobile: true,
            cell: (row) => (
                <span className="tabular-nums">{row.school_classes_count}</span>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
                <RowActions
                    showHref={`/master/departments/${row.id}`}
                    editHref={`/master/departments/${row.id}/edit`}
                    deleteHref={`/master/departments/${row.id}`}
                    deleteDescription={`Jurusan "${row.name}" akan dihapus. Jurusan yang masih memiliki kelas tidak dapat dihapus.`}
                    canUpdate={can('departments.update')}
                    canDelete={can('departments.delete')}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Jurusan" description="Kelola daftar jurusan sekolah">
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari kode atau nama jurusan..."
                >
                    {can('departments.create') ? (
                        <Button asChild>
                            <Link href="/master/departments/create">
                                <Plus className="size-4" aria-hidden="true" />
                                Tambah Jurusan
                            </Link>
                        </Button>
                    ) : null}
                </DataTableToolbar>

                <DataTable
                    columns={columns}
                    page={departments}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada jurusan."
                />

                <DataTablePagination
                    page={departments}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
