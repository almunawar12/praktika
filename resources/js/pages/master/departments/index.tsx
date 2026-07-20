import {
    BookOpen,
    CircleCheck,
    CirclePause,
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
    DepartmentDetail,
    type DepartmentRecord,
} from '@/components/master/department-detail';
import {
    DepartmentFields,
    type DepartmentValues,
} from '@/components/master/department-form';
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

type Department = {
    id: string;
    code: string;
    name: string;
    is_active: boolean;
    school_classes_count: number;
    students_count: number;
};

type Stats = {
    total: number;
    active: number;
    inactive: number;
    students: number;
};

type Props = {
    departments: Paginated<Department>;
    table: TableState;
    stats: Stats;
};

type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

const PER_PAGE_OPTIONS = ['10', '15', '25', '50'];

export default function DepartmentIndex({ departments, table, stats }: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, goToPage, setPerPage } = useTableQuery(
        '/master/departments',
        table,
    );

    const [modal, setModal] = useState<Modal>(null);
    const [deleting, setDeleting] = useState<{ id: string; name: string } | null>(
        null,
    );

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
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {departments.from !== null
                        ? departments.from + index
                        : index + 1}
                </span>
            ),
        },
        {
            key: 'code',
            header: 'Kode Jurusan',
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
            key: 'students',
            header: 'Jumlah Siswa',
            hideOnMobile: true,
            cell: (row) => (
                <span className="tabular-nums">{row.students_count}</span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) =>
                row.is_active ? (
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
                    {can('departments.update') ? (
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
                    {can('departments.delete') ? (
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
            title="Master Data - Jurusan"
            headTitle="Jurusan"
            description="Kelola data jurusan yang ada di sekolah. Data jurusan digunakan untuk mengelompokkan kelas dan siswa."
            actions={
                can('departments.create') ? (
                    <Button onClick={() => setModal({ mode: 'create' })}>
                        <Plus className="size-4" aria-hidden="true" />
                        Tambah Jurusan
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={BookOpen}
                        tone="blue"
                        label="Total Jurusan"
                        value={stats.total}
                        hint="Semua jurusan"
                    />
                    <StatCard
                        icon={CircleCheck}
                        tone="green"
                        label="Aktif"
                        value={stats.active}
                        hint="Sedang digunakan"
                    />
                    <StatCard
                        icon={CirclePause}
                        tone="amber"
                        label="Nonaktif"
                        value={stats.inactive}
                        hint="Tidak digunakan"
                    />
                    <StatCard
                        icon={Users}
                        tone="violet"
                        label="Total Siswa"
                        value={stats.students}
                        hint="Di semua jurusan"
                    />
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Select
                                    value={String(departments.per_page)}
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

                            <div className="relative sm:w-72">
                                <Search
                                    className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Cari jurusan..."
                                    className="pl-9"
                                    aria-label="Cari jurusan"
                                />
                            </div>
                        </div>

                        <DataTable
                            columns={columns}
                            page={departments}
                            state={table}
                            onSort={sortBy}
                            rowKey={(row) => row.id}
                            emptyMessage="Belum ada jurusan."
                        />

                        <TablePager
                            page={departments}
                            onPageChange={goToPage}
                        />
                    </CardContent>
                </Card>
            </div>

            <FormModal<DepartmentValues>
                open={modal?.mode === 'create'}
                onOpenChange={(open) => !open && close()}
                title="Tambah Jurusan"
                description="Buat jurusan baru"
                action="/master/departments"
                method="post"
                initialData={{ code: '', name: '', is_active: true }}
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
                    initialData={{
                        code: edit.data.code,
                        name: edit.data.name,
                        is_active: edit.data.is_active,
                    }}
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
                                setModal({ mode: 'edit', id: detail.data!.id })
                            }
                        >
                            Ubah
                        </Button>
                    ) : undefined
                }
            >
                {detail.data ? <DepartmentDetail record={detail.data} /> : null}
            </DetailModal>

            <DeleteDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                action={deleting ? `/master/departments/${deleting.id}` : ''}
                description={
                    deleting
                        ? `Jurusan "${deleting.name}" akan dihapus. Jurusan yang masih memiliki kelas tidak dapat dihapus.`
                        : ''
                }
            />
        </AppLayout>
    );
}
