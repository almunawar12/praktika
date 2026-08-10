import { router } from '@inertiajs/react';
import {
    CalendarRange,
    CircleCheck,
    Pencil,
    Plus,
    Search,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { TablePager } from '@/components/data-table/table-pager';
import { AcademicYearDetail } from '@/components/master/academic-year-detail';
import type { AcademicYearRecord } from '@/components/master/academic-year-detail';
import { AcademicYearFields } from '@/components/master/academic-year-form';
import type { AcademicYearValues } from '@/components/master/academic-year-form';
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

type AcademicYear = {
    id: string;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    school_classes_count: number;
    placements_count: number;
};

type Stats = {
    total: number;
    active: number;
};

type Props = {
    academicYears: Paginated<AcademicYear>;
    table: TableState;
    stats: Stats;
};

type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

const PER_PAGE_OPTIONS = ['10', '15', '25', '50'];

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
        new Date(iso),
    );
}

export default function AcademicYearIndex({
    academicYears,
    table,
    stats,
}: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, goToPage, setPerPage } = useTableQuery(
        '/master/academic-years',
        table,
    );

    const [modal, setModal] = useState<Modal>(null);
    const [deleting, setDeleting] = useState<{
        id: string;
        name: string;
    } | null>(null);
    const [activating, setActivating] = useState<string | null>(null);

    const detailUrl =
        modal?.mode === 'detail' && modal.id
            ? `/master/academic-years/${modal.id}`
            : null;
    const editUrl =
        modal?.mode === 'edit' && modal.id
            ? `/master/academic-years/${modal.id}/edit`
            : null;

    const detail = useResource<AcademicYearRecord>(detailUrl);
    const edit = useResource<AcademicYearRecord>(editUrl);

    const close = () => setModal(null);

    const activate = (id: string) => {
        setActivating(id);
        router.put(
            `/master/academic-years/${id}/activate`,
            {},
            { preserveScroll: true, onFinish: () => setActivating(null) },
        );
    };

    const columns: Column<AcademicYear>[] = [
        {
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {academicYears.from !== null
                        ? academicYears.from + index
                        : index + 1}
                </span>
            ),
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
            key: 'period',
            header: 'Periode',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-xs tabular-nums">
                    {formatDate(row.start_date)} – {formatDate(row.end_date)}
                </span>
            ),
        },
        {
            key: 'classes',
            header: 'Kelas',
            hideOnMobile: true,
            cell: (row) => (
                <span className="tabular-nums">{row.school_classes_count}</span>
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
                    <Badge variant="secondary">Tidak Aktif</Badge>
                ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            className: 'w-32',
            cell: (row) => (
                <div className="flex items-center gap-1">
                    {!row.is_active && can('academic-years.activate') ? (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="size-8 text-muted-foreground hover:text-foreground"
                            disabled={activating === row.id}
                            onClick={() => activate(row.id)}
                        >
                            <CircleCheck
                                className="size-4"
                                aria-hidden="true"
                            />
                            <span className="sr-only">Aktifkan {row.name}</span>
                        </Button>
                    ) : null}
                    {can('academic-years.update') ? (
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
                    {can('academic-years.delete') ? (
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
            title="Master Data - Tahun Ajaran"
            headTitle="Tahun Ajaran"
            description="Kelola tahun ajaran. Tahun ajaran aktif menjadi acuan default penempatan dan kelas baru."
            actions={
                can('academic-years.create') ? (
                    <Button onClick={() => setModal({ mode: 'create' })}>
                        <Plus className="size-4" aria-hidden="true" />
                        Tambah Tahun Ajaran
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard
                        icon={CalendarRange}
                        tone="blue"
                        label="Total Tahun Ajaran"
                        value={stats.total}
                        hint="Semua tahun ajaran"
                    />
                    <StatCard
                        icon={CircleCheck}
                        tone="green"
                        label="Aktif"
                        value={stats.active}
                        hint="Sedang berjalan"
                    />
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Select
                                    value={String(academicYears.per_page)}
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
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    aria-hidden="true"
                                />
                                <Input
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    placeholder="Cari tahun ajaran..."
                                    className="pl-9"
                                    aria-label="Cari tahun ajaran"
                                />
                            </div>
                        </div>

                        <DataTable
                            columns={columns}
                            page={academicYears}
                            state={table}
                            onSort={sortBy}
                            rowKey={(row) => row.id}
                            emptyMessage="Belum ada tahun ajaran."
                        />

                        <TablePager
                            page={academicYears}
                            onPageChange={goToPage}
                        />
                    </CardContent>
                </Card>
            </div>

            <FormModal<AcademicYearValues>
                open={modal?.mode === 'create'}
                onOpenChange={(open) => !open && close()}
                title="Tambah Tahun Ajaran"
                description="Buat tahun ajaran baru"
                action="/master/academic-years"
                method="post"
                initialData={{ name: '', start_date: '', end_date: '' }}
                submitLabel="Simpan"
            >
                {(fields) => <AcademicYearFields {...fields} />}
            </FormModal>

            {edit.data ? (
                <FormModal<AcademicYearValues>
                    open={modal?.mode === 'edit'}
                    onOpenChange={(open) => !open && close()}
                    title="Ubah Tahun Ajaran"
                    description="Perbarui data tahun ajaran"
                    action={`/master/academic-years/${edit.data.id}`}
                    method="put"
                    initialData={{
                        name: edit.data.name,
                        start_date: edit.data.start_date.slice(0, 10),
                        end_date: edit.data.end_date.slice(0, 10),
                    }}
                    submitLabel="Perbarui"
                >
                    {(fields) => <AcademicYearFields {...fields} />}
                </FormModal>
            ) : null}

            <DetailModal
                open={modal?.mode === 'detail'}
                onOpenChange={(open) => !open && close()}
                title={detail.data?.name ?? 'Detail Tahun Ajaran'}
                description="Detail tahun ajaran"
                loading={detail.loading}
                error={detail.error}
                footer={
                    can('academic-years.update') && detail.data ? (
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
                    <AcademicYearDetail record={detail.data} />
                ) : null}
            </DetailModal>

            <DeleteDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                action={deleting ? `/master/academic-years/${deleting.id}` : ''}
                description={
                    deleting
                        ? `Tahun ajaran "${deleting.name}" akan dihapus. Tahun ajaran yang masih memiliki penempatan tidak dapat dihapus.`
                        : ''
                }
            />
        </AppLayout>
    );
}
