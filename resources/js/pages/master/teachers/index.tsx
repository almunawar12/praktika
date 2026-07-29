import {
    CircleCheck,
    CirclePause,
    ClipboardCheck,
    Pencil,
    Plus,
    Search,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { TablePager } from '@/components/data-table/table-pager';
import { TeacherDetail } from '@/components/master/teacher-detail';
import type { TeacherRecord } from '@/components/master/teacher-detail';
import { TeacherFields } from '@/components/master/teacher-form';
import type { TeacherValues } from '@/components/master/teacher-form';
import { DeleteDialog } from '@/components/shared/delete-dialog';
import { DetailModal } from '@/components/shared/detail-modal';
import { FormModal } from '@/components/shared/form-modal';
import { StatCard } from '@/components/shared/stat-card';
import { Badge } from '@/components/ui/badge';
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
import type { Paginated, TableState } from '@/types/table';

type Teacher = {
    id: string;
    nip: string;
    name: string;
    phone: string | null;
    user: { id: string; email: string; is_active: boolean };
};

type Stats = {
    total: number;
    active: number;
    inactive: number;
    supervising: number;
};

type Props = {
    teachers: Paginated<Teacher>;
    table: TableState;
    stats: Stats;
};

type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

const PER_PAGE_OPTIONS = ['10', '15', '25', '50'];

export default function TeacherIndex({ teachers, table, stats }: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/teachers', table);

    const [modal, setModal] = useState<Modal>(null);
    const [deleting, setDeleting] = useState<{
        id: string;
        name: string;
    } | null>(null);

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
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {teachers.from !== null ? teachers.from + index : index + 1}
                </span>
            ),
        },
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
            key: 'status',
            header: 'Status',
            cell: (row) =>
                row.user.is_active ? (
                    <Badge className="border-transparent bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                        Aktif
                    </Badge>
                ) : (
                    <Badge variant="secondary">Nonaktif</Badge>
                ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            className: 'w-24',
            cell: (row) => (
                <div className="flex items-center gap-1">
                    {can('teachers.update') ? (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground hover:text-foreground"
                            onClick={() =>
                                setModal({ mode: 'edit', id: row.id })
                            }
                        >
                            <Pencil className="size-4" aria-hidden="true" />
                            <span className="sr-only">Ubah {row.name}</span>
                        </Button>
                    ) : null}
                    {can('teachers.delete') ? (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground hover:text-destructive"
                            onClick={() =>
                                setDeleting({ id: row.id, name: row.name })
                            }
                        >
                            <Trash2 className="size-4" aria-hidden="true" />
                            <span className="sr-only">Hapus {row.name}</span>
                        </Button>
                    ) : null}
                </div>
            ),
        },
    ];

    return (
        <AppLayout
            title="Master Data - Guru"
            headTitle="Guru"
            description="Kelola data guru pembimbing beserta akun login dan bimbingannya."
            actions={
                can('teachers.create') ? (
                    <Button onClick={() => setModal({ mode: 'create' })}>
                        <Plus className="size-4" aria-hidden="true" />
                        Tambah Guru
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={Users}
                        tone="blue"
                        label="Total Guru"
                        value={stats.total}
                        hint="Semua guru pembimbing"
                    />
                    <StatCard
                        icon={CircleCheck}
                        tone="green"
                        label="Akun Aktif"
                        value={stats.active}
                        hint="Bisa login"
                    />
                    <StatCard
                        icon={CirclePause}
                        tone="amber"
                        label="Akun Nonaktif"
                        value={stats.inactive}
                        hint="Tidak bisa login"
                    />
                    <StatCard
                        icon={ClipboardCheck}
                        tone="violet"
                        label="Membimbing"
                        value={stats.supervising}
                        hint="Punya bimbingan PKL"
                    />
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Select
                                    value={String(teachers.per_page)}
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

                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                <Select
                                    value={table.filters.is_active ?? 'all'}
                                    onValueChange={(value) =>
                                        filterBy('is_active', value)
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
                                        <SelectItem value="active">
                                            Aktif
                                        </SelectItem>
                                        <SelectItem value="inactive">
                                            Nonaktif
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                <div className="relative sm:w-72">
                                    <Search
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Cari NIP, nama, atau email..."
                                        className="pl-9"
                                        aria-label="Cari guru"
                                    />
                                </div>
                            </div>
                        </div>

                        <DataTable
                            columns={columns}
                            page={teachers}
                            state={table}
                            onSort={sortBy}
                            rowKey={(row) => row.id}
                            emptyMessage="Belum ada guru."
                        />

                        <TablePager page={teachers} onPageChange={goToPage} />
                    </CardContent>
                </Card>
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

            <DeleteDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                action={deleting ? `/master/teachers/${deleting.id}` : ''}
                description={
                    deleting
                        ? `Guru "${deleting.name}" akan dihapus dan akun loginnya dinonaktifkan. Riwayat aktivitasnya tetap tersimpan.`
                        : ''
                }
            />
        </AppLayout>
    );
}
