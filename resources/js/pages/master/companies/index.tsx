import {
    Building2,
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
    CompanyDetail,
    type CompanyRecord,
} from '@/components/master/company-detail';
import {
    CompanyFields,
    type CompanyValues,
} from '@/components/master/company-form';
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

type Company = {
    id: string;
    name: string;
    business_field: string;
    pic_name: string;
    pic_phone: string;
    quota: number;
    is_active: boolean;
};

type Stats = {
    total: number;
    active: number;
    inactive: number;
    quota: number;
};

type Props = {
    companies: Paginated<Company>;
    table: TableState;
    stats: Stats;
};

type EditRecord = CompanyRecord & {
    latitude: string | number | null;
    longitude: string | number | null;
    geofence_radius_m: number | null;
};

type Modal = { mode: 'create' | 'edit' | 'detail'; id?: string } | null;

const PER_PAGE_OPTIONS = ['10', '15', '25', '50'];

function toField(value: string | number | null): string {
    return value === null ? '' : String(value);
}

export default function CompanyIndex({ companies, table, stats }: Props) {
    const { can } = usePermission();
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/master/companies', table);

    const [modal, setModal] = useState<Modal>(null);
    const [deleting, setDeleting] = useState<{ id: string; name: string } | null>(
        null,
    );

    const detailUrl =
        modal?.mode === 'detail' && modal.id
            ? `/master/companies/${modal.id}`
            : null;
    const editUrl =
        modal?.mode === 'edit' && modal.id
            ? `/master/companies/${modal.id}/edit`
            : null;

    const detail = useResource<CompanyRecord>(detailUrl);
    const edit = useResource<EditRecord>(editUrl);

    const close = () => setModal(null);

    const columns: Column<Company>[] = [
        {
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {companies.from !== null
                        ? companies.from + index
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
            key: 'business_field',
            header: 'Bidang Usaha',
            sortable: true,
            cell: (row) => (
                <Badge variant="outline">{row.business_field}</Badge>
            ),
        },
        {
            key: 'pic',
            header: 'PIC',
            hideOnMobile: true,
            cell: (row) => (
                <div className="grid gap-0.5">
                    <span>{row.pic_name}</span>
                    <span className="text-xs text-muted-foreground">
                        {row.pic_phone}
                    </span>
                </div>
            ),
        },
        {
            key: 'quota',
            header: 'Kuota',
            sortable: true,
            cell: (row) => <span className="tabular-nums">{row.quota}</span>,
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
                    {can('companies.update') ? (
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
                    {can('companies.delete') ? (
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
            title="Master Data - Perusahaan"
            headTitle="Perusahaan"
            description="Kelola data DUDI tempat PKL beserta kuota dan status penerimaannya."
            actions={
                can('companies.create') ? (
                    <Button onClick={() => setModal({ mode: 'create' })}>
                        <Plus className="size-4" aria-hidden="true" />
                        Tambah Perusahaan
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={Building2}
                        tone="blue"
                        label="Total Perusahaan"
                        value={stats.total}
                        hint="Semua DUDI"
                    />
                    <StatCard
                        icon={CircleCheck}
                        tone="green"
                        label="Aktif"
                        value={stats.active}
                        hint="Menerima siswa"
                    />
                    <StatCard
                        icon={CirclePause}
                        tone="amber"
                        label="Nonaktif"
                        value={stats.inactive}
                        hint="Tidak menerima"
                    />
                    <StatCard
                        icon={Users}
                        tone="violet"
                        label="Total Kuota"
                        value={stats.quota}
                        hint="Slot PKL tersedia"
                    />
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Select
                                    value={String(companies.per_page)}
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
                                        className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                                        aria-hidden="true"
                                    />
                                    <Input
                                        value={search}
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Cari nama, bidang usaha, atau PIC..."
                                        className="pl-9"
                                        aria-label="Cari perusahaan"
                                    />
                                </div>
                            </div>
                        </div>

                        <DataTable
                            columns={columns}
                            page={companies}
                            state={table}
                            onSort={sortBy}
                            rowKey={(row) => row.id}
                            emptyMessage="Belum ada perusahaan."
                        />

                        <TablePager page={companies} onPageChange={goToPage} />
                    </CardContent>
                </Card>
            </div>

            <FormModal<CompanyValues>
                open={modal?.mode === 'create'}
                onOpenChange={(open) => !open && close()}
                title="Tambah Perusahaan"
                description="Buat data perusahaan tempat PKL"
                action="/master/companies"
                method="post"
                initialData={{
                    name: '',
                    business_field: '',
                    address: '',
                    pic_name: '',
                    pic_phone: '',
                    quota: 0,
                    is_active: true,
                    latitude: '',
                    longitude: '',
                    geofence_radius_m: '',
                }}
                submitLabel="Simpan"
            >
                {(fields) => <CompanyFields {...fields} />}
            </FormModal>

            {edit.data ? (
                <FormModal<CompanyValues>
                    open={modal?.mode === 'edit'}
                    onOpenChange={(open) => !open && close()}
                    title="Ubah Perusahaan"
                    description="Perbarui data perusahaan"
                    action={`/master/companies/${edit.data.id}`}
                    method="put"
                    initialData={{
                        name: edit.data.name,
                        business_field: edit.data.business_field,
                        address: edit.data.address,
                        pic_name: edit.data.pic_name,
                        pic_phone: edit.data.pic_phone,
                        quota: edit.data.quota,
                        is_active: edit.data.is_active,
                        latitude: toField(edit.data.latitude),
                        longitude: toField(edit.data.longitude),
                        geofence_radius_m: toField(edit.data.geofence_radius_m),
                    }}
                    submitLabel="Perbarui"
                >
                    {(fields) => <CompanyFields {...fields} />}
                </FormModal>
            ) : null}

            <DetailModal
                open={modal?.mode === 'detail'}
                onOpenChange={(open) => !open && close()}
                title={detail.data?.name ?? 'Detail Perusahaan'}
                description="Detail perusahaan"
                loading={detail.loading}
                error={detail.error}
                footer={
                    can('companies.update') && detail.data ? (
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
                {detail.data ? <CompanyDetail record={detail.data} /> : null}
            </DetailModal>

            <DeleteDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                action={deleting ? `/master/companies/${deleting.id}` : ''}
                description={
                    deleting
                        ? `Perusahaan "${deleting.name}" akan dihapus. Jika hanya ingin menghentikan sementara, ubah statusnya menjadi nonaktif.`
                        : ''
                }
            />
        </AppLayout>
    );
}
