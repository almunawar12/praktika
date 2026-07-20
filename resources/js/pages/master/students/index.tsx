import { Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import {
    StudentDetail,
    type StudentRecord,
} from '@/components/master/student-detail';
import {
    StudentFields,
    type StudentValues,
} from '@/components/master/student-form';
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

type EditRecord = StudentRecord & { class_id: string };
type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

export default function StudentIndex({
    students,
    table,
    classes,
    departments,
}: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/students', table);

    const [modal, setModal] = useState<Modal>(null);

    const detailUrl =
        modal?.mode === 'detail' && modal.id
            ? `/master/students/${modal.id}`
            : null;
    const editUrl =
        modal?.mode === 'edit' && modal.id
            ? `/master/students/${modal.id}/edit`
            : null;

    const detail = useResource<StudentRecord>(detailUrl);
    const edit = useResource<EditRecord>(editUrl);

    const close = () => setModal(null);

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
                    onShow={() => setModal({ mode: 'detail', id: row.id })}
                    onEdit={() => setModal({ mode: 'edit', id: row.id })}
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
                        <Button onClick={() => setModal({ mode: 'create' })}>
                            <Plus className="size-4" aria-hidden="true" />
                            Tambah Siswa
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

            <FormModal<StudentValues>
                open={modal?.mode === 'create'}
                onOpenChange={(open) => !open && close()}
                title="Tambah Siswa"
                description="Buat data siswa dan akun loginnya"
                action="/master/students"
                method="post"
                initialData={{
                    nis: '',
                    nisn: '',
                    name: '',
                    email: '',
                    phone: '',
                    class_id: '',
                }}
                submitLabel="Simpan"
            >
                {(fields) => (
                    <StudentFields
                        {...fields}
                        classes={classes}
                        mode="create"
                    />
                )}
            </FormModal>

            {edit.data ? (
                <FormModal<StudentValues>
                    open={modal?.mode === 'edit'}
                    onOpenChange={(open) => !open && close()}
                    title="Ubah Siswa"
                    description="Perbarui data siswa"
                    action={`/master/students/${edit.data.id}`}
                    method="put"
                    initialData={{
                        nis: edit.data.nis,
                        nisn: edit.data.nisn,
                        name: edit.data.name,
                        email: edit.data.user.email,
                        phone: edit.data.phone ?? '',
                        class_id: edit.data.class_id,
                    }}
                    submitLabel="Perbarui"
                >
                    {(fields) => (
                        <StudentFields
                            {...fields}
                            classes={classes}
                            mode="edit"
                        />
                    )}
                </FormModal>
            ) : null}

            <DetailModal
                open={modal?.mode === 'detail'}
                onOpenChange={(open) => !open && close()}
                title={detail.data?.name ?? 'Detail Siswa'}
                description="Detail siswa peserta PKL"
                loading={detail.loading}
                error={detail.error}
                footer={
                    can('students.update') && detail.data ? (
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
                {detail.data ? <StudentDetail record={detail.data} /> : null}
            </DetailModal>
        </AppLayout>
    );
}
