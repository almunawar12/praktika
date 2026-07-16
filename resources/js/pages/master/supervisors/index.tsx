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

type Supervisor = {
    id: string;
    name: string;
    position: string | null;
    phone: string | null;
    user: { id: string; email: string; is_active: boolean };
    company: { id: string; name: string };
};

type Props = {
    supervisors: Paginated<Supervisor>;
    table: TableState;
    companies: SelectOption[];
};

export default function SupervisorIndex({
    supervisors,
    table,
    companies,
}: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/supervisors', table);

    const columns: Column<Supervisor>[] = [
        {
            key: 'name',
            header: 'Nama',
            sortable: true,
            cell: (row) => (
                <Link
                    href={`/master/supervisors/${row.id}`}
                    className="grid gap-0.5 underline-offset-4 hover:underline"
                >
                    <span className="font-medium">{row.name}</span>
                    {row.position ? (
                        <span className="text-xs text-muted-foreground">
                            {row.position}
                        </span>
                    ) : null}
                </Link>
            ),
        },
        {
            key: 'company',
            header: 'Perusahaan',
            cell: (row) => <Badge variant="outline">{row.company.name}</Badge>,
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
                    showHref={`/master/supervisors/${row.id}`}
                    editHref={`/master/supervisors/${row.id}/edit`}
                    deleteHref={`/master/supervisors/${row.id}`}
                    deleteDescription={`Pembimbing "${row.name}" akan dihapus dan akun loginnya dinonaktifkan. Riwayat verifikasi dan review tetap tersimpan.`}
                    canUpdate={can('company-supervisors.update')}
                    canDelete={can('company-supervisors.delete')}
                />
            ),
        },
    ];

    return (
        <AppLayout
            title="Pembimbing Industri"
            description="Kelola pembimbing dari pihak perusahaan"
        >
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari nama, jabatan, email, atau perusahaan..."
                    filters={[
                        {
                            key: 'company_id',
                            label: 'Perusahaan',
                            value: table.filters.company_id ?? 'all',
                            options: companies,
                        },
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
                    {can('company-supervisors.create') ? (
                        <Button asChild>
                            <Link href="/master/supervisors/create">
                                <Plus className="size-4" aria-hidden="true" />
                                Tambah Pembimbing
                            </Link>
                        </Button>
                    ) : null}
                </DataTableToolbar>

                <DataTable
                    columns={columns}
                    page={supervisors}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada pembimbing industri."
                />

                <DataTablePagination
                    page={supervisors}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
