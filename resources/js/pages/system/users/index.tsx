import { Link } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, SelectOption, TableState } from '@/types/table';

type UserRow = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    role: string | null;
    role_label: string | null;
    last_login_at: string | null;
};

type Props = {
    users: Paginated<UserRow>;
    table: TableState;
    roles: SelectOption[];
};

function formatDate(value: string | null): string {
    return value === null
        ? 'Belum pernah'
        : new Date(value).toLocaleString('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          });
}

export default function UserIndex({ users, table, roles }: Props) {
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/system/users', table);

    const columns: Column<UserRow>[] = [
        {
            key: 'name',
            header: 'Nama',
            sortable: true,
            cell: (row) => (
                <Link
                    href={`/system/users/${row.id}/edit`}
                    className="grid gap-0.5 underline-offset-4 hover:underline"
                >
                    <span className="font-medium">{row.name}</span>
                    <span className="text-xs text-muted-foreground">
                        {row.email}
                    </span>
                </Link>
            ),
        },
        {
            key: 'role',
            header: 'Role',
            cell: (row) =>
                row.role_label ? (
                    <Badge variant="secondary">{row.role_label}</Badge>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        Tanpa role
                    </span>
                ),
        },
        {
            key: 'is_active',
            header: 'Status',
            cell: (row) =>
                row.is_active ? (
                    <Badge className="border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400">
                        Aktif
                    </Badge>
                ) : (
                    <Badge variant="secondary">Nonaktif</Badge>
                ),
        },
        {
            key: 'last_login_at',
            header: 'Login Terakhir',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-sm text-muted-foreground">
                    {formatDate(row.last_login_at)}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-16',
            cell: (row) => (
                <Button variant="ghost" size="sm" asChild>
                    <Link href={`/system/users/${row.id}/edit`}>Kelola</Link>
                </Button>
            ),
        },
    ];

    return (
        <AppLayout title="Pengguna" description="Kelola akun dan akses">
            <div className="flex flex-col gap-4">
                <div className="flex justify-end">
                    <Button asChild>
                        <Link href="/system/users/create">
                            <Plus className="size-4" aria-hidden="true" />
                            Tambah Pengguna
                        </Link>
                    </Button>
                </div>

                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari nama atau email..."
                    filters={[
                        {
                            key: 'role',
                            label: 'Role',
                            value: table.filters.role ?? 'all',
                            options: roles,
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            value: table.filters.status ?? 'all',
                            options: [
                                { value: 'active', label: 'Aktif' },
                                { value: 'inactive', label: 'Nonaktif' },
                            ],
                        },
                    ]}
                    onFilterChange={filterBy}
                />

                <DataTable
                    columns={columns}
                    page={users}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada pengguna."
                />

                <DataTablePagination
                    page={users}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
