import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import {
    PlacementDetail,
    type PlacementDetailData,
    type PlacementRecord,
} from '@/components/pkl/placement-detail';
import {
    PlacementFields,
    type PlacementFormOptions,
    type PlacementValues,
} from '@/components/pkl/placement-form';
import { PlacementStatusBadge } from '@/components/pkl/placement-status-badge';
import type { PlacementStatus } from '@/components/pkl/placement-status-badge';
import { DeleteDialog } from '@/components/shared/delete-dialog';
import { DetailModal } from '@/components/shared/detail-modal';
import { FormModal } from '@/components/shared/form-modal';
import { Button } from '@/components/ui/button';
import { usePermission } from '@/hooks/use-permission';
import { useResource } from '@/hooks/use-resource';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, SelectOption, TableState } from '@/types/table';

type Placement = {
    id: string;
    status: PlacementStatus;
    start_date: string;
    end_date: string;
    student: {
        id: string;
        name: string;
        nis: string;
        school_class: {
            id: string;
            name: string;
            department: { code: string };
        };
    };
    company: { id: string; name: string };
    teacher: { id: string; name: string };
};

type Props = {
    placements: Paginated<Placement>;
    table: TableState;
    statuses: SelectOption[];
    companies: SelectOption[];
    teachers: SelectOption[];
};

type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
        new Date(iso),
    );
}

export default function PlacementIndex({
    placements,
    table,
    statuses,
    companies,
    teachers,
}: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/pkl/placements', table);

    const [modal, setModal] = useState<Modal>(null);
    const [deletingId, setDeletingId] = useState<string | null>(null);

    const optionsUrl =
        modal?.mode === 'create' || modal?.mode === 'edit'
            ? '/pkl/placement-options'
            : null;
    const detailUrl =
        modal?.mode === 'detail' && modal.id
            ? `/pkl/placements/${modal.id}`
            : null;
    const editUrl =
        modal?.mode === 'edit' && modal.id
            ? `/pkl/placements/${modal.id}/edit`
            : null;

    const options = useResource<PlacementFormOptions>(optionsUrl);
    const detail = useResource<PlacementDetailData>(detailUrl);
    const edit = useResource<PlacementRecord>(editUrl);

    const close = () => setModal(null);

    const columns: Column<Placement>[] = [
        {
            key: 'student',
            header: 'Siswa',
            cell: (row) => (
                <button
                    type="button"
                    onClick={() => setModal({ mode: 'detail', id: row.id })}
                    className="grid gap-0.5 text-left underline-offset-4 hover:underline"
                >
                    <span className="font-medium">{row.student.name}</span>
                    <span className="text-xs text-muted-foreground tabular-nums">
                        {row.student.nis} · {row.student.school_class.name}
                    </span>
                </button>
            ),
        },
        {
            key: 'company',
            header: 'Perusahaan',
            cell: (row) => row.company.name,
        },
        {
            key: 'teacher',
            header: 'Guru Pembimbing',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-muted-foreground">
                    {row.teacher.name}
                </span>
            ),
        },
        {
            key: 'start_date',
            header: 'Periode',
            sortable: true,
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-xs tabular-nums">
                    {formatDate(row.start_date)} – {formatDate(row.end_date)}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            sortable: true,
            cell: (row) => <PlacementStatusBadge status={row.status} />,
        },
        {
            key: 'actions',
            header: '',
            className: 'w-12',
            cell: (row) => (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setModal({ mode: 'detail', id: row.id })}
                >
                    Detail
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            title="Penempatan PKL"
            description="Kelola penempatan siswa ke perusahaan"
        >
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari siswa, NIS, perusahaan, atau guru..."
                    filters={[
                        {
                            key: 'status',
                            label: 'Status',
                            value: table.filters.status ?? 'all',
                            options: statuses,
                        },
                        {
                            key: 'company_id',
                            label: 'Perusahaan',
                            value: table.filters.company_id ?? 'all',
                            options: companies,
                        },
                        {
                            key: 'teacher_id',
                            label: 'Guru',
                            value: table.filters.teacher_id ?? 'all',
                            options: teachers,
                        },
                    ]}
                    onFilterChange={filterBy}
                >
                    {can('placements.create') ? (
                        <Button onClick={() => setModal({ mode: 'create' })}>
                            <Plus className="size-4" aria-hidden="true" />
                            Tambah Penempatan
                        </Button>
                    ) : null}
                </DataTableToolbar>

                <DataTable
                    columns={columns}
                    page={placements}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada penempatan."
                />

                <DataTablePagination
                    page={placements}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>

            {modal?.mode === 'create' && options.data ? (
                <FormModal<PlacementValues>
                    open
                    onOpenChange={(open) => !open && close()}
                    title="Tambah Penempatan"
                    description="Buat draft penempatan siswa ke perusahaan"
                    action="/pkl/placements"
                    method="post"
                    initialData={{
                        student_id: '',
                        company_id: '',
                        teacher_id: '',
                        start_date: '',
                        end_date: '',
                    }}
                    submitLabel="Simpan"
                >
                    {(fields) => (
                        <PlacementFields
                            {...fields}
                            options={options.data!}
                            mode="create"
                        />
                    )}
                </FormModal>
            ) : null}

            {modal?.mode === 'edit' && options.data && edit.data ? (
                <FormModal<PlacementValues>
                    open
                    onOpenChange={(open) => !open && close()}
                    title="Ubah Penempatan"
                    description="Perbarui data penempatan"
                    action={`/pkl/placements/${edit.data.id}`}
                    method="put"
                    initialData={{
                        student_id: edit.data.student.id,
                        company_id: edit.data.company.id,
                        teacher_id: edit.data.teacher.id,
                        start_date: edit.data.start_date.slice(0, 10),
                        end_date: edit.data.end_date.slice(0, 10),
                    }}
                    submitLabel="Perbarui"
                >
                    {(fields) => (
                        <PlacementFields
                            {...fields}
                            options={options.data!}
                            mode="edit"
                        />
                    )}
                </FormModal>
            ) : null}

            <DetailModal
                open={modal?.mode === 'detail'}
                onOpenChange={(open) => !open && close()}
                title={
                    detail.data
                        ? `Penempatan — ${detail.data.placement.student.name}`
                        : 'Detail Penempatan'
                }
                description="Detail penempatan PKL"
                loading={detail.loading}
                error={detail.error}
                footer={
                    detail.data ? (
                        <div className="flex flex-wrap gap-2">
                            {detail.data.can.update ? (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        setModal({
                                            mode: 'edit',
                                            id: detail.data!.placement.id,
                                        })
                                    }
                                >
                                    Ubah
                                </Button>
                            ) : null}
                            {detail.data.can.delete ? (
                                <Button
                                    variant="ghost"
                                    className="text-destructive"
                                    onClick={() =>
                                        setDeletingId(detail.data!.placement.id)
                                    }
                                >
                                    <Trash2
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Hapus Draft
                                </Button>
                            ) : null}
                        </div>
                    ) : undefined
                }
            >
                {detail.data ? <PlacementDetail {...detail.data} /> : null}
            </DetailModal>

            <DeleteDialog
                open={deletingId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeletingId(null);
                        close();
                    }
                }}
                action={deletingId ? `/pkl/placements/${deletingId}` : ''}
                title="Hapus draft penempatan?"
                description="Hanya draft yang dapat dihapus. Penempatan yang sudah berjalan harus dibatalkan agar riwayatnya tetap tersimpan."
            />
        </AppLayout>
    );
}
