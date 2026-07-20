import {
    Building2,
    CircleCheck,
    CirclePause,
    Pencil,
    Plus,
    Search,
    Trash2,
    UserCog,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { TablePager } from '@/components/data-table/table-pager';
import {
    SupervisorDetail,
    type SupervisorRecord,
} from '@/components/master/supervisor-detail';
import {
    SupervisorFields,
    type SupervisorValues,
} from '@/components/master/supervisor-form';
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

type Supervisor = {
    id: string;
    name: string;
    position: string | null;
    phone: string | null;
    user: { id: string; email: string; is_active: boolean };
    company: { id: string; name: string };
};

type Stats = {
    total: number;
    active: number;
    inactive: number;
    companies: number;
};

type Props = {
    supervisors: Paginated<Supervisor>;
    table: TableState;
    companies: SelectOption[];
    stats: Stats;
};

type EditRecord = SupervisorRecord & { company_id: string };
type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

const PER_PAGE_OPTIONS = ['10', '15', '25', '50'];

export default function SupervisorIndex({
    supervisors,
    table,
    companies,
    stats,
}: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/supervisors', table);

    const [modal, setModal] = useState<Modal>(null);
    const [deleting, setDeleting] = useState<{ id: string; name: string } | null>(
        null,
    );

    const detailUrl =
        modal?.mode === 'detail' && modal.id
            ? `/master/supervisors/${modal.id}`
            : null;
    const editUrl =
        modal?.mode === 'edit' && modal.id
            ? `/master/supervisors/${modal.id}/edit`
            : null;

    const detail = useResource<SupervisorRecord>(detailUrl);
    const edit = useResource<EditRecord>(editUrl);

    const close = () => setModal(null);

    const columns: Column<Supervisor>[] = [
        {
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {supervisors.from !== null
                        ? supervisors.from + index
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
                    className="grid gap-0.5 text-left underline-offset-4 hover:underline"
                >
                    <span className="font-medium">{row.name}</span>
                    {row.position ? (
                        <span className="text-xs text-muted-foreground">
                            {row.position}
                        </span>
                    ) : null}
                </button>
            ),
        },
        {
            key: 'company',
            header: 'Perusahaan',
            cell: (row) => <Badge variant="outline">{row.company.name}</Badge>,
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
                    {can('company-supervisors.update') ? (
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
                    {can('company-supervisors.delete') ? (
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
            title="Master Data - Pembimbing Industri"
            headTitle="Pembimbing Industri"
            description="Kelola pembimbing dari pihak perusahaan beserta akun login dan penempatannya."
            actions={
                can('company-supervisors.create') ? (
                    <Button onClick={() => setModal({ mode: 'create' })}>
                        <Plus className="size-4" aria-hidden="true" />
                        Tambah Pembimbing
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={UserCog}
                        tone="blue"
                        label="Total Pembimbing"
                        value={stats.total}
                        hint="Semua pembimbing industri"
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
                        icon={Building2}
                        tone="violet"
                        label="Perusahaan"
                        value={stats.companies}
                        hint="Punya pembimbing"
                    />
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Select
                                    value={String(supervisors.per_page)}
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
                                    value={table.filters.company_id ?? 'all'}
                                    onValueChange={(value) =>
                                        filterBy('company_id', value)
                                    }
                                >
                                    <SelectTrigger
                                        className="sm:w-48"
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

                                <Select
                                    value={table.filters.is_active ?? 'all'}
                                    onValueChange={(value) =>
                                        filterBy('is_active', value)
                                    }
                                >
                                    <SelectTrigger
                                        className="sm:w-36"
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
                                        placeholder="Cari nama, jabatan, email..."
                                        className="pl-9"
                                        aria-label="Cari pembimbing"
                                    />
                                </div>
                            </div>
                        </div>

                        <DataTable
                            columns={columns}
                            page={supervisors}
                            state={table}
                            onSort={sortBy}
                            rowKey={(row) => row.id}
                            emptyMessage="Belum ada pembimbing industri."
                        />

                        <TablePager page={supervisors} onPageChange={goToPage} />
                    </CardContent>
                </Card>
            </div>

            <FormModal<SupervisorValues>
                open={modal?.mode === 'create'}
                onOpenChange={(open) => !open && close()}
                title="Tambah Pembimbing"
                description="Buat data pembimbing industri dan akun loginnya"
                action="/master/supervisors"
                method="post"
                initialData={{
                    company_id: '',
                    name: '',
                    email: '',
                    position: '',
                    phone: '',
                }}
                submitLabel="Simpan"
            >
                {(fields) => (
                    <SupervisorFields
                        {...fields}
                        companies={companies}
                        mode="create"
                    />
                )}
            </FormModal>

            {edit.data ? (
                <FormModal<SupervisorValues>
                    open={modal?.mode === 'edit'}
                    onOpenChange={(open) => !open && close()}
                    title="Ubah Pembimbing"
                    description="Perbarui data pembimbing industri"
                    action={`/master/supervisors/${edit.data.id}`}
                    method="put"
                    initialData={{
                        company_id: edit.data.company_id,
                        name: edit.data.name,
                        email: edit.data.user.email,
                        position: edit.data.position ?? '',
                        phone: edit.data.phone ?? '',
                    }}
                    submitLabel="Perbarui"
                >
                    {(fields) => (
                        <SupervisorFields
                            {...fields}
                            companies={companies}
                            mode="edit"
                        />
                    )}
                </FormModal>
            ) : null}

            <DetailModal
                open={modal?.mode === 'detail'}
                onOpenChange={(open) => !open && close()}
                title={detail.data?.name ?? 'Detail Pembimbing'}
                description="Detail pembimbing industri"
                loading={detail.loading}
                error={detail.error}
                footer={
                    can('company-supervisors.update') && detail.data ? (
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
                    <SupervisorDetail record={detail.data} />
                ) : null}
            </DetailModal>

            <DeleteDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                action={deleting ? `/master/supervisors/${deleting.id}` : ''}
                description={
                    deleting
                        ? `Pembimbing "${deleting.name}" akan dihapus dan akun loginnya dinonaktifkan. Riwayat verifikasi dan review tetap tersimpan.`
                        : ''
                }
            />
        </AppLayout>
    );
}
