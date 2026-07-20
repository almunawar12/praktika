import { Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import {
    SupervisorDetail,
    type SupervisorRecord,
} from '@/components/master/supervisor-detail';
import {
    SupervisorFields,
    type SupervisorValues,
} from '@/components/master/supervisor-form';
import { DetailModal } from '@/components/shared/detail-modal';
import { FormModal } from '@/components/shared/form-modal';
import { RowActions } from '@/components/shared/row-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { useResource } from '@/hooks/use-resource';
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

type EditRecord = SupervisorRecord & { company_id: string };
type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

export default function SupervisorIndex({
    supervisors,
    table,
    companies,
}: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/supervisors', table);

    const [modal, setModal] = useState<Modal>(null);

    const detailUrl =
        modal?.mode === 'detail' && modal.id
            ? `/master/supervisors/${modal.id}`
            : null;
    const editUrl =
        modal?.mode === 'edit' && modal.id
            ? `/master/supervisors/${modal.id}/edit`
            : null;

    const detail = useResource<SupervisorRecord>(detailUrl);
    const edit = useResource<EditRecord>(editUrl);

    const close = () => setModal(null);

    const columns: Column<Supervisor>[] = [
        {
            key: 'name',
            header: 'Nama',
            sortable: true,
            cell: (row) => (
                <button
                    type="button"
                    onClick={() => setModal({ mode: 'detail', id: row.id })}
                    className="grid gap-0.5 text-left underline-offset-4 hover:underline"
                >
                    <span className="font-medium">{row.name}</span>
                    {row.position ? (
                        <span className="text-xs text-muted-foreground">
                            {row.position}
                        </span>
                    ) : null}
                </button>
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
                    onShow={() => setModal({ mode: 'detail', id: row.id })}
                    onEdit={() => setModal({ mode: 'edit', id: row.id })}
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
                        <Button onClick={() => setModal({ mode: 'create' })}>
                            <Plus className="size-4" aria-hidden="true" />
                            Tambah Pembimbing
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

            <FormModal<SupervisorValues>
                open={modal?.mode === 'create'}
                onOpenChange={(open) => !open && close()}
                title="Tambah Pembimbing"
                description="Buat data pembimbing industri dan akun loginnya"
                action="/master/supervisors"
                method="post"
                initialData={{
                    company_id: '',
                    name: '',
                    email: '',
                    position: '',
                    phone: '',
                }}
                submitLabel="Simpan"
            >
                {(fields) => (
                    <SupervisorFields
                        {...fields}
                        companies={companies}
                        mode="create"
                    />
                )}
            </FormModal>

            {edit.data ? (
                <FormModal<SupervisorValues>
                    open={modal?.mode === 'edit'}
                    onOpenChange={(open) => !open && close()}
                    title="Ubah Pembimbing"
                    description="Perbarui data pembimbing industri"
                    action={`/master/supervisors/${edit.data.id}`}
                    method="put"
                    initialData={{
                        company_id: edit.data.company_id,
                        name: edit.data.name,
                        email: edit.data.user.email,
                        position: edit.data.position ?? '',
                        phone: edit.data.phone ?? '',
                    }}
                    submitLabel="Perbarui"
                >
                    {(fields) => (
                        <SupervisorFields
                            {...fields}
                            companies={companies}
                            mode="edit"
                        />
                    )}
                </FormModal>
            ) : null}

            <DetailModal
                open={modal?.mode === 'detail'}
                onOpenChange={(open) => !open && close()}
                title={detail.data?.name ?? 'Detail Pembimbing'}
                description="Detail pembimbing industri"
                loading={detail.loading}
                error={detail.error}
                footer={
                    can('company-supervisors.update') && detail.data ? (
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
                {detail.data ? (
                    <SupervisorDetail record={detail.data} />
                ) : null}
            </DetailModal>
        </AppLayout>
    );
}
