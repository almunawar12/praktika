import {
    CircleCheck,
    CirclePause,
    MapPin,
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
import {
    StudentDetail,
    type StudentRecord,
} from '@/components/master/student-detail';
import {
    StudentFields,
    type StudentValues,
} from '@/components/master/student-form';
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

type Stats = {
    total: number;
    active: number;
    inactive: number;
    placed: number;
};

type Props = {
    students: Paginated<Student>;
    table: TableState;
    classes: SelectOption[];
    departments: SelectOption[];
    stats: Stats;
};

type EditRecord = StudentRecord & { class_id: string };
type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

const PER_PAGE_OPTIONS = ['10', '15', '25', '50'];

export default function StudentIndex({
    students,
    table,
    classes,
    departments,
    stats,
}: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/students', table);

    const [modal, setModal] = useState<Modal>(null);
    const [deleting, setDeleting] = useState<{ id: string; name: string } | null>(
        null,
    );

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
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {students.from !== null ? students.from + index : index + 1}
                </span>
            ),
        },
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
                    {can('students.update') ? (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground hover:text-foreground"
                            onClick={() => setModal({ mode: 'edit', id: row.id })}
                        >
                            <Pencil className="size-4" aria-hidden="true" />
                            <span className="sr-only">Ubah {row.name}</span>
                        </Button>
                    ) : null}
                    {can('students.delete') ? (
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
            title="Master Data - Siswa"
            headTitle="Siswa"
            description="Kelola data siswa peserta PKL beserta akun login dan penempatannya."
            actions={
                can('students.create') ? (
                    <Button onClick={() => setModal({ mode: 'create' })}>
                        <Plus className="size-4" aria-hidden="true" />
                        Tambah Siswa
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={Users}
                        tone="blue"
                        label="Total Siswa"
                        value={stats.total}
                        hint="Semua siswa"
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
                        icon={MapPin}
                        tone="violet"
                        label="Sudah Ditempatkan"
                        value={stats.placed}
                        hint="Punya penempatan PKL"
                    />
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Select
                                    value={String(students.per_page)}
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
                                    value={table.filters.department_id ?? 'all'}
                                    onValueChange={(value) =>
                                        filterBy('department_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        className="sm:w-44"
                                        aria-label="Filter jurusan"
                                    >
                                        <SelectValue placeholder="Semua jurusan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua jurusan
                                        </SelectItem>
                                        {departments.map((option) => (
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
                                    value={table.filters.class_id ?? 'all'}
                                    onValueChange={(value) =>
                                        filterBy('class_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        className="sm:w-44"
                                        aria-label="Filter kelas"
                                    >
                                        <SelectValue placeholder="Semua kelas" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua kelas
                                        </SelectItem>
                                        {classes.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <div className="relative sm:w-64">
                                    <Search
                                        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Cari NIS, NISN, nama, email..."
                                        className="pl-9"
                                        aria-label="Cari siswa"
                                    />
                                </div>
                            </div>
                        </div>

                        <DataTable
                            columns={columns}
                            page={students}
                            state={table}
                            onSort={sortBy}
                            rowKey={(row) => row.id}
                            emptyMessage="Belum ada siswa."
                        />

                        <TablePager page={students} onPageChange={goToPage} />
                    </CardContent>
                </Card>
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

            <DeleteDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                action={deleting ? `/master/students/${deleting.id}` : ''}
                description={
                    deleting
                        ? `Siswa "${deleting.name}" akan dihapus dan akun loginnya dinonaktifkan. Riwayat aktivitasnya tetap tersimpan.`
                        : ''
                }
            />
        </AppLayout>
    );
}
