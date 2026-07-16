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

type Student = {
    id: string;
    nis: string;
    nisn: string;
    name: string;
    phone: string | null;
    user: { id: string; email: string; is_active: boolean };
    school_class: {
        id: string;
        name: string;
        department: { id: string; code: string; name: string };
    };
};

type Props = {
    students: Paginated<Student>;
    table: TableState;
    classes: SelectOption[];
    departments: SelectOption[];
};

export default function StudentIndex({
    students,
    table,
    classes,
    departments,
}: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/students', table);

    const columns: Column<Student>[] = [
        {
            key: 'nis',
            header: 'NIS',
            sortable: true,
            hideOnMobile: true,
            cell: (row) => <span className="tabular-nums">{row.nis}</span>,
        },
        {
            key: 'name',
            header: 'Nama',
            sortable: true,
            cell: (row) => (
                <Link
                    href={`/master/students/${row.id}`}
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {row.name}
                </Link>
            ),
        },
        {
            key: 'class',
            header: 'Kelas',
            cell: (row) => (
                <Badge variant="outline">
                    {row.school_class.name} ({row.school_class.department.code})
                </Badge>
            ),
        },
        {
            key: 'email',
            header: 'Email',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-muted-foreground">{row.user.email}</span>
            ),
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
                    showHref={`/master/students/${row.id}`}
                    editHref={`/master/students/${row.id}/edit`}
                    deleteHref={`/master/students/${row.id}`}
                    deleteDescription={`Siswa "${row.name}" akan dihapus dan akun loginnya dinonaktifkan. Riwayat aktivitasnya tetap tersimpan.`}
                    canUpdate={can('students.update')}
                    canDelete={can('students.delete')}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Siswa" description="Kelola data siswa peserta PKL">
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari NIS, NISN, nama, atau email..."
                    filters={[
                        {
                            key: 'department_id',
                            label: 'Jurusan',
                            value: table.filters.department_id ?? 'all',
                            options: departments,
                        },
                        {
                            key: 'class_id',
                            label: 'Kelas',
                            value: table.filters.class_id ?? 'all',
                            options: classes,
                        },
                    ]}
                    onFilterChange={filterBy}
                >
                    {can('students.create') ? (
                        <Button asChild>
                            <Link href="/master/students/create">
                                <Plus className="size-4" aria-hidden="true" />
                                Tambah Siswa
                            </Link>
                        </Button>
                    ) : null}
                </DataTableToolbar>

                <DataTable
                    columns={columns}
                    page={students}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada siswa."
                />

                <DataTablePagination
                    page={students}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
