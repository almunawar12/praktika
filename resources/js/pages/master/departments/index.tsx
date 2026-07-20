import { Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import {
    DepartmentDetail,
    type DepartmentRecord,
} from '@/components/master/department-detail';
import {
    DepartmentFields,
    type DepartmentValues,
} from '@/components/master/department-form';
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

type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

export default function DepartmentIndex({ departments, table }: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, goToPage, setPerPage } = useTableQuery(
        '/master/departments',
        table,
    );

    const [modal, setModal] = useState<Modal>(null);

    const detailUrl =
        modal?.mode === 'detail' && modal.id
            ? `/master/departments/${modal.id}`
            : null;
    const editUrl =
        modal?.mode === 'edit' && modal.id
            ? `/master/departments/${modal.id}/edit`
            : null;

    const detail = useResource<DepartmentRecord>(detailUrl);
    const edit = useResource<DepartmentRecord>(editUrl);

    const close = () => setModal(null);

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
                    onShow={() => setModal({ mode: 'detail', id: row.id })}
                    onEdit={() => setModal({ mode: 'edit', id: row.id })}
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
                        <Button onClick={() => setModal({ mode: 'create' })}>
                            <Plus className="size-4" aria-hidden="true" />
                            Tambah Jurusan
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

            <FormModal<DepartmentValues>
                open={modal?.mode === 'create'}
                onOpenChange={(open) => !open && close()}
                title="Tambah Jurusan"
                description="Buat jurusan baru"
                action="/master/departments"
                method="post"
                initialData={{ code: '', name: '' }}
                submitLabel="Simpan"
            >
                {(fields) => <DepartmentFields {...fields} />}
            </FormModal>

            {edit.data ? (
                <FormModal<DepartmentValues>
                    open={modal?.mode === 'edit'}
                    onOpenChange={(open) => !open && close()}
                    title="Ubah Jurusan"
                    description="Perbarui data jurusan"
                    action={`/master/departments/${edit.data.id}`}
                    method="put"
                    initialData={{ code: edit.data.code, name: edit.data.name }}
                    submitLabel="Perbarui"
                >
                    {(fields) => <DepartmentFields {...fields} />}
                </FormModal>
            ) : null}

            <DetailModal
                open={modal?.mode === 'detail'}
                onOpenChange={(open) => !open && close()}
                title={detail.data?.name ?? 'Detail Jurusan'}
                description="Detail jurusan"
                loading={detail.loading}
                error={detail.error}
                footer={
                    can('departments.update') && detail.data ? (
                        <Button
                            onClick={() =>
                                setModal({
                                    mode: 'edit',
                                    id: detail.data!.id,
                                })
                            }
                        >
                            Ubah
                        </Button>
                    ) : undefined
                }
            >
                {detail.data ? <DepartmentDetail record={detail.data} /> : null}
            </DetailModal>
        </AppLayout>
    );
}
