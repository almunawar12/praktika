import { Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import {
    SchoolClassDetail,
    type SchoolClassRecord,
} from '@/components/master/school-class-detail';
import {
    SchoolClassFields,
    type SchoolClassValues,
} from '@/components/master/school-class-form';
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

type SchoolClass = {
    id: string;
    name: string;
    students_count: number;
    department: { id: string; code: string; name: string };
};

type Props = {
    classes: Paginated<SchoolClass>;
    table: TableState;
    departments: SelectOption[];
};

type EditRecord = SchoolClassRecord & { department_id: string };
type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

export default function ClassIndex({ classes, table, departments }: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/classes', table);

    const [modal, setModal] = useState<Modal>(null);

    const detailUrl =
        modal?.mode === 'detail' && modal.id
            ? `/master/classes/${modal.id}`
            : null;
    const editUrl =
        modal?.mode === 'edit' && modal.id
            ? `/master/classes/${modal.id}/edit`
            : null;

    const detail = useResource<SchoolClassRecord>(detailUrl);
    const edit = useResource<EditRecord>(editUrl);

    const close = () => setModal(null);

    const columns: Column<SchoolClass>[] = [
        {
            key: 'name',
            header: 'Nama Kelas',
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
            key: 'department',
            header: 'Jurusan',
            cell: (row) => (
                <Badge variant="outline">
                    {row.department.code} — {row.department.name}
                </Badge>
            ),
        },
        {
            key: 'students',
            header: 'Jumlah Siswa',
            hideOnMobile: true,
            cell: (row) => (
                <span className="tabular-nums">{row.students_count}</span>
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
                    deleteHref={`/master/classes/${row.id}`}
                    deleteDescription={`Kelas "${row.name}" akan dihapus. Kelas yang masih memiliki siswa tidak dapat dihapus.`}
                    canUpdate={can('school-classes.update')}
                    canDelete={can('school-classes.delete')}
                />
            ),
        },
    ];

    return (
        <AppLayout title="Kelas" description="Kelola daftar kelas per jurusan">
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari nama kelas atau jurusan..."
                    filters={[
                        {
                            key: 'department_id',
                            label: 'Jurusan',
                            value: table.filters.department_id ?? 'all',
                            options: departments,
                        },
                    ]}
                    onFilterChange={filterBy}
                >
                    {can('school-classes.create') ? (
                        <Button onClick={() => setModal({ mode: 'create' })}>
                            <Plus className="size-4" aria-hidden="true" />
                            Tambah Kelas
                        </Button>
                    ) : null}
                </DataTableToolbar>

                <DataTable
                    columns={columns}
                    page={classes}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada kelas."
                />

                <DataTablePagination
                    page={classes}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>

            <FormModal<SchoolClassValues>
                open={modal?.mode === 'create'}
                onOpenChange={(open) => !open && close()}
                title="Tambah Kelas"
                description="Buat kelas baru"
                action="/master/classes"
                method="post"
                initialData={{ department_id: '', name: '' }}
                submitLabel="Simpan"
            >
                {(fields) => (
                    <SchoolClassFields {...fields} departments={departments} />
                )}
            </FormModal>

            {edit.data ? (
                <FormModal<SchoolClassValues>
                    open={modal?.mode === 'edit'}
                    onOpenChange={(open) => !open && close()}
                    title="Ubah Kelas"
                    description="Perbarui data kelas"
                    action={`/master/classes/${edit.data.id}`}
                    method="put"
                    initialData={{
                        department_id: edit.data.department_id,
                        name: edit.data.name,
                    }}
                    submitLabel="Perbarui"
                >
                    {(fields) => (
                        <SchoolClassFields
                            {...fields}
                            departments={departments}
                        />
                    )}
                </FormModal>
            ) : null}

            <DetailModal
                open={modal?.mode === 'detail'}
                onOpenChange={(open) => !open && close()}
                title={detail.data?.name ?? 'Detail Kelas'}
                description="Detail kelas"
                loading={detail.loading}
                error={detail.error}
                footer={
                    can('school-classes.update') && detail.data ? (
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
                    <SchoolClassDetail record={detail.data} />
                ) : null}
            </DetailModal>
        </AppLayout>
    );
}
