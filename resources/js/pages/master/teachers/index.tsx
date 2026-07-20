import { Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import {
    TeacherDetail,
    type TeacherRecord,
} from '@/components/master/teacher-detail';
import {
    TeacherFields,
    type TeacherValues,
} from '@/components/master/teacher-form';
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

type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

export default function TeacherIndex({ teachers, table }: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/teachers', table);

    const [modal, setModal] = useState<Modal>(null);

    const detailUrl =
        modal?.mode === 'detail' && modal.id
            ? `/master/teachers/${modal.id}`
            : null;
    const editUrl =
        modal?.mode === 'edit' && modal.id
            ? `/master/teachers/${modal.id}/edit`
            : null;

    const detail = useResource<TeacherRecord>(detailUrl);
    const edit = useResource<TeacherRecord>(editUrl);

    const close = () => setModal(null);

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
                    onShow={() => setModal({ mode: 'detail', id: row.id })}
                    onEdit={() => setModal({ mode: 'edit', id: row.id })}
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
                        <Button onClick={() => setModal({ mode: 'create' })}>
                            <Plus className="size-4" aria-hidden="true" />
                            Tambah Guru
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

            <FormModal<TeacherValues>
                open={modal?.mode === 'create'}
                onOpenChange={(open) => !open && close()}
                title="Tambah Guru"
                description="Buat data guru dan akun loginnya"
                action="/master/teachers"
                method="post"
                initialData={{ nip: '', name: '', email: '', phone: '' }}
                submitLabel="Simpan"
            >
                {(fields) => <TeacherFields {...fields} mode="create" />}
            </FormModal>

            {edit.data ? (
                <FormModal<TeacherValues>
                    open={modal?.mode === 'edit'}
                    onOpenChange={(open) => !open && close()}
                    title="Ubah Guru"
                    description="Perbarui data guru"
                    action={`/master/teachers/${edit.data.id}`}
                    method="put"
                    initialData={{
                        nip: edit.data.nip,
                        name: edit.data.name,
                        email: edit.data.user.email,
                        phone: edit.data.phone ?? '',
                    }}
                    submitLabel="Perbarui"
                >
                    {(fields) => <TeacherFields {...fields} mode="edit" />}
                </FormModal>
            ) : null}

            <DetailModal
                open={modal?.mode === 'detail'}
                onOpenChange={(open) => !open && close()}
                title={detail.data?.name ?? 'Detail Guru'}
                description="Detail guru pembimbing"
                loading={detail.loading}
                error={detail.error}
                footer={
                    can('teachers.update') && detail.data ? (
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
                {detail.data ? <TeacherDetail record={detail.data} /> : null}
            </DetailModal>
        </AppLayout>
    );
}
