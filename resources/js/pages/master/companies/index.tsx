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

type Company = {
    id: string;
    name: string;
    business_field: string;
    pic_name: string;
    pic_phone: string;
    quota: number;
    is_active: boolean;
};

type Props = {
    companies: Paginated<Company>;
    table: TableState;
};

export default function CompanyIndex({ companies, table }: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/companies', table);

    const columns: Column<Company>[] = [
        {
            key: 'name',
            header: 'Nama',
            sortable: true,
            cell: (row) => (
                <Link
                    href={`/master/companies/${row.id}`}
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {row.name}
                </Link>
            ),
        },
        {
            key: 'business_field',
            header: 'Bidang Usaha',
            sortable: true,
            cell: (row) => (
                <Badge variant="outline">{row.business_field}</Badge>
            ),
        },
        {
            key: 'pic',
            header: 'PIC',
            hideOnMobile: true,
            cell: (row) => (
                <div className="grid gap-0.5">
                    <span>{row.pic_name}</span>
                    <span className="text-xs text-muted-foreground">
                        {row.pic_phone}
                    </span>
                </div>
            ),
        },
        {
            key: 'quota',
            header: 'Kuota',
            sortable: true,
            cell: (row) => <span className="tabular-nums">{row.quota}</span>,
        },
        {
            key: 'is_active',
            header: 'Status',
            cell: (row) =>
                row.is_active ? (
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
                    showHref={`/master/companies/${row.id}`}
                    editHref={`/master/companies/${row.id}/edit`}
                    deleteHref={`/master/companies/${row.id}`}
                    deleteDescription={`Perusahaan "${row.name}" akan dihapus. Jika hanya ingin menghentikan sementara, ubah statusnya menjadi nonaktif.`}
                    canUpdate={can('companies.update')}
                    canDelete={can('companies.delete')}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Perusahaan" description="Kelola data DUDI tempat PKL">
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari nama, bidang usaha, atau PIC..."
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
                    {can('companies.create') ? (
                        <Button asChild>
                            <Link href="/master/companies/create">
                                <Plus className="size-4" aria-hidden="true" />
                                Tambah Perusahaan
                            </Link>
                        </Button>
                    ) : null}
                </DataTableToolbar>

                <DataTable
                    columns={columns}
                    page={companies}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada perusahaan."
                />

                <DataTablePagination
                    page={companies}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
