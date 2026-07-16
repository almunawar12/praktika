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

type Teacher = {
    id: string;
    nip: string;
    name: string;
    phone: string | null;
    user: { id: string; email: string; is_active: boolean };
};

type Props = {
    teachers: Paginated<Teacher>;
    table: TableState;
};

export default function TeacherIndex({ teachers, table }: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/teachers', table);

    const columns: Column<Teacher>[] = [
        {
            key: 'nip',
            header: 'NIP',
            sortable: true,
            hideOnMobile: true,
            cell: (row) => <span className="tabular-nums">{row.nip}</span>,
        },
        {
            key: 'name',
            header: 'Nama',
            sortable: true,
            cell: (row) => (
                <Link
                    href={`/master/teachers/${row.id}`}
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {row.name}
                </Link>
            ),
        },
        {
            key: 'email',
            header: 'Email',
            cell: (row) => (
                <span className="text-muted-foreground">{row.user.email}</span>
            ),
        },
        {
            key: 'phone',
            header: 'No HP',
            hideOnMobile: true,
            cell: (row) =>
                row.phone ?? <span className="text-muted-foreground">—</span>,
        },
        {
            key: 'status',
            header: 'Akun',
            cell: (row) =>
                row.user.is_active ? (
                    <Badge variant="secondary">Aktif</Badge>
                ) : (
                    <Badge variant="outline">Nonaktif</Badge>
                ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
                <RowActions
                    showHref={`/master/teachers/${row.id}`}
                    editHref={`/master/teachers/${row.id}/edit`}
                    deleteHref={`/master/teachers/${row.id}`}
                    deleteDescription={`Guru "${row.name}" akan dihapus dan akun loginnya dinonaktifkan. Riwayat aktivitasnya tetap tersimpan.`}
                    canUpdate={can('teachers.update')}
                    canDelete={can('teachers.delete')}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Guru" description="Kelola data guru pembimbing">
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari NIP, nama, atau email..."
                    filters={[
                        {
                            key: 'is_active',
                            label: 'Status',
                            value: table.filters.is_active ?? 'all',
                            options: [
                                { value: 'active', label: 'Aktif' },
                                { value: 'inactive', label: 'Nonaktif' },
                            ],
                        },
                    ]}
                    onFilterChange={filterBy}
                >
                    {can('teachers.create') ? (
                        <Button asChild>
                            <Link href="/master/teachers/create">
                                <Plus className="size-4" aria-hidden="true" />
                                Tambah Guru
                            </Link>
                        </Button>
                    ) : null}
                </DataTableToolbar>

                <DataTable
                    columns={columns}
                    page={teachers}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada guru."
                />

                <DataTablePagination
                    page={teachers}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
