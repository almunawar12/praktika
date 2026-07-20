import { Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import {
    CompanyDetail,
    type CompanyRecord,
} from '@/components/master/company-detail';
import {
    CompanyFields,
    type CompanyValues,
} from '@/components/master/company-form';
import { DetailModal } from '@/components/shared/detail-modal';
import { FormModal } from '@/components/shared/form-modal';
import { RowActions } from '@/components/shared/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { useResource } from '@/hooks/use-resource';
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

type EditRecord = CompanyRecord & {
    latitude: string | number | null;
    longitude: string | number | null;
    geofence_radius_m: number | null;
};

type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

function toField(value: string | number | null): string {
    return value === null ? '' : String(value);
}

export default function CompanyIndex({ companies, table }: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/companies', table);

    const [modal, setModal] = useState<Modal>(null);

    const detailUrl =
        modal?.mode === 'detail' && modal.id
            ? `/master/companies/${modal.id}`
            : null;
    const editUrl =
        modal?.mode === 'edit' && modal.id
            ? `/master/companies/${modal.id}/edit`
            : null;

    const detail = useResource<CompanyRecord>(detailUrl);
    const edit = useResource<EditRecord>(editUrl);

    const close = () => setModal(null);

    const columns: Column<Company>[] = [
        {
            key: 'name',
            header: 'Nama',
            sortable: true,
            cell: (row) => (
                <button
                    type="button"
                    onClick={() => setModal({ mode: 'detail', id: row.id })}
                    className="font-medium underline-offset-4 hover:underline"
                >
                    {row.name}
                </button>
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
                    onShow={() => setModal({ mode: 'detail', id: row.id })}
                    onEdit={() => setModal({ mode: 'edit', id: row.id })}
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
                        <Button onClick={() => setModal({ mode: 'create' })}>
                            <Plus className="size-4" aria-hidden="true" />
                            Tambah Perusahaan
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

            <FormModal<CompanyValues>
                open={modal?.mode === 'create'}
                onOpenChange={(open) => !open && close()}
                title="Tambah Perusahaan"
                description="Buat data perusahaan tempat PKL"
                action="/master/companies"
                method="post"
                initialData={{
                    name: '',
                    business_field: '',
                    address: '',
                    pic_name: '',
                    pic_phone: '',
                    quota: 0,
                    is_active: true,
                    latitude: '',
                    longitude: '',
                    geofence_radius_m: '',
                }}
                submitLabel="Simpan"
            >
                {(fields) => <CompanyFields {...fields} />}
            </FormModal>

            {edit.data ? (
                <FormModal<CompanyValues>
                    open={modal?.mode === 'edit'}
                    onOpenChange={(open) => !open && close()}
                    title="Ubah Perusahaan"
                    description="Perbarui data perusahaan"
                    action={`/master/companies/${edit.data.id}`}
                    method="put"
                    initialData={{
                        name: edit.data.name,
                        business_field: edit.data.business_field,
                        address: edit.data.address,
                        pic_name: edit.data.pic_name,
                        pic_phone: edit.data.pic_phone,
                        quota: edit.data.quota,
                        is_active: edit.data.is_active,
                        latitude: toField(edit.data.latitude),
                        longitude: toField(edit.data.longitude),
                        geofence_radius_m: toField(edit.data.geofence_radius_m),
                    }}
                    submitLabel="Perbarui"
                >
                    {(fields) => <CompanyFields {...fields} />}
                </FormModal>
            ) : null}

            <DetailModal
                open={modal?.mode === 'detail'}
                onOpenChange={(open) => !open && close()}
                title={detail.data?.name ?? 'Detail Perusahaan'}
                description="Detail perusahaan"
                loading={detail.loading}
                error={detail.error}
                footer={
                    can('companies.update') && detail.data ? (
                        <Button
                            onClick={() =>
                                setModal({ mode: 'edit', id: detail.data!.id })
                            }
                        >
                            Ubah
                        </Button>
                    ) : undefined
                }
            >
                {detail.data ? <CompanyDetail record={detail.data} /> : null}
            </DetailModal>
        </AppLayout>
    );
}
