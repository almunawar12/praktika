import {
    CircleCheck,
    CirclePause,
    Pencil,
    Plus,
    School,
    Search,
    Trash2,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { TablePager } from '@/components/data-table/table-pager';
import { SchoolClassDetail } from '@/components/master/school-class-detail';
import type { SchoolClassRecord } from '@/components/master/school-class-detail';
import { SchoolClassFields } from '@/components/master/school-class-form';
import type { SchoolClassValues } from '@/components/master/school-class-form';
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

type SchoolClass = {
    id: string;
    name: string;
    is_active: boolean;
    students_count: number;
    department: { id: string; code: string; name: string };
};

type Stats = {
    total: number;
    active: number;
    inactive: number;
    students: number;
};

type Props = {
    classes: Paginated<SchoolClass>;
    table: TableState;
    departments: SelectOption[];
    academicYears: SelectOption[];
    stats: Stats;
};

type EditRecord = SchoolClassRecord & {
    department_id: string;
    academic_year_id: string | null;
};
type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

const PER_PAGE_OPTIONS = ['10', '15', '25', '50'];

export default function ClassIndex({
    classes,
    table,
    departments,
    academicYears,
    stats,
}: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/classes', table);

    const [modal, setModal] = useState<Modal>(null);
    const [deleting, setDeleting] = useState<{
        id: string;
        name: string;
    } | null>(null);

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
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {classes.from !== null ? classes.from + index : index + 1}
                </span>
            ),
        },
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
                    {can('school-classes.update') ? (
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
                    {can('school-classes.delete') ? (
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
            title="Master Data - Kelas"
            headTitle="Kelas"
            description="Kelola data kelas per jurusan. Kelas mengelompokkan siswa untuk penempatan dan monitoring PKL."
            actions={
                can('school-classes.create') ? (
                    <Button onClick={() => setModal({ mode: 'create' })}>
                        <Plus className="size-4" aria-hidden="true" />
                        Tambah Kelas
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={School}
                        tone="blue"
                        label="Total Kelas"
                        value={stats.total}
                        hint="Semua kelas"
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
                        hint="Di semua kelas"
                    />
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Select
                                    value={String(classes.per_page)}
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
                                        className="sm:w-56"
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
                                        placeholder="Cari nama kelas atau jurusan..."
                                        className="pl-9"
                                        aria-label="Cari kelas"
                                    />
                                </div>
                            </div>
                        </div>

                        <DataTable
                            columns={columns}
                            page={classes}
                            state={table}
                            onSort={sortBy}
                            rowKey={(row) => row.id}
                            emptyMessage="Belum ada kelas."
                        />

                        <TablePager page={classes} onPageChange={goToPage} />
                    </CardContent>
                </Card>
            </div>

            <FormModal<SchoolClassValues>
                open={modal?.mode === 'create'}
                onOpenChange={(open) => !open && close()}
                title="Tambah Kelas"
                description="Buat kelas baru"
                action="/master/classes"
                method="post"
                initialData={{
                    department_id: '',
                    academic_year_id: '',
                    name: '',
                    is_active: true,
                }}
                submitLabel="Simpan"
            >
                {(fields) => (
                    <SchoolClassFields
                        {...fields}
                        departments={departments}
                        academicYears={academicYears}
                    />
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
                        academic_year_id: edit.data.academic_year_id ?? '',
                        name: edit.data.name,
                        is_active: edit.data.is_active,
                    }}
                    submitLabel="Perbarui"
                >
                    {(fields) => (
                        <SchoolClassFields
                            {...fields}
                            departments={departments}
                            academicYears={academicYears}
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

            <DeleteDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                action={deleting ? `/master/classes/${deleting.id}` : ''}
                description={
                    deleting
                        ? `Kelas "${deleting.name}" akan dihapus. Kelas yang masih memiliki siswa tidak dapat dihapus.`
                        : ''
                }
            />
        </AppLayout>
    );
}
