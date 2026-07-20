import {
    CheckCircle2,
    FilePen,
    MapPin,
    PlayCircle,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { TablePager } from '@/components/data-table/table-pager';
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
import { StatCard } from '@/components/shared/stat-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

type Stats = {
    total: number;
    active: number;
    completed: number;
    draft: number;
};

type Props = {
    placements: Paginated<Placement>;
    table: TableState;
    statuses: SelectOption[];
    companies: SelectOption[];
    teachers: SelectOption[];
    stats: Stats;
};

type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

const PER_PAGE_OPTIONS = ['10', '15', '25', '50'];

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
    stats,
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
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {placements.from !== null
                        ? placements.from + index
                        : index + 1}
                </span>
            ),
        },
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
            header: 'Aksi',
            className: 'w-20',
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
            description="Kelola penempatan siswa ke perusahaan beserta status PKL-nya."
            actions={
                can('placements.create') ? (
                    <Button onClick={() => setModal({ mode: 'create' })}>
                        <Plus className="size-4" aria-hidden="true" />
                        Tambah Penempatan
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={MapPin}
                        tone="blue"
                        label="Total Penempatan"
                        value={stats.total}
                        hint="Semua penempatan"
                    />
                    <StatCard
                        icon={PlayCircle}
                        tone="green"
                        label="Aktif"
                        value={stats.active}
                        hint="Sedang berjalan"
                    />
                    <StatCard
                        icon={CheckCircle2}
                        tone="violet"
                        label="Selesai"
                        value={stats.completed}
                        hint="PKL rampung"
                    />
                    <StatCard
                        icon={FilePen}
                        tone="amber"
                        label="Draft"
                        value={stats.draft}
                        hint="Belum diaktifkan"
                    />
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Select
                                    value={String(placements.per_page)}
                                    onValueChange={setPerPage}
                                >
                                    <SelectTrigger
                                        className="w-[72px]"
                                        aria-label="Jumlah baris per halaman"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {PER_PAGE_OPTIONS.map((option) => (
                                            <SelectItem
                                                key={option}
                                                value={option}
                                            >
                                                {option}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                data per halaman
                            </div>

                            <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                                <Select
                                    value={table.filters.status ?? 'all'}
                                    onValueChange={(value) =>
                                        filterBy('status', value)
                                    }
                                >
                                    <SelectTrigger
                                        className="sm:w-40"
                                        aria-label="Filter status"
                                    >
                                        <SelectValue placeholder="Semua status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua status
                                        </SelectItem>
                                        {statuses.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Select
                                    value={table.filters.company_id ?? 'all'}
                                    onValueChange={(value) =>
                                        filterBy('company_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        className="sm:w-44"
                                        aria-label="Filter perusahaan"
                                    >
                                        <SelectValue placeholder="Semua perusahaan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua perusahaan
                                        </SelectItem>
                                        {companies.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <div className="relative sm:w-60">
                                    <Search
                                        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Cari siswa, NIS, perusahaan..."
                                        className="pl-9"
                                        aria-label="Cari penempatan"
                                    />
                                </div>
                            </div>
                        </div>

                        <DataTable
                            columns={columns}
                            page={placements}
                            state={table}
                            onSort={sortBy}
                            rowKey={(row) => row.id}
                            emptyMessage="Belum ada penempatan."
                        />

                        <TablePager page={placements} onPageChange={goToPage} />
                    </CardContent>
                </Card>
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
