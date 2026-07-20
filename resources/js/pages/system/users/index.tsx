import {
    CircleCheck,
    CirclePause,
    LogIn,
    Plus,
    Search,
    Users,
} from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { TablePager } from '@/components/data-table/table-pager';
import { UserFields, type UserValues } from '@/components/system/user-form';
import {
    UserModerationActions,
    type UserAbilities,
} from '@/components/system/user-moderation-actions';
import { DeleteDialog } from '@/components/shared/delete-dialog';
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
import { useResource } from '@/hooks/use-resource';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, SelectOption, TableState } from '@/types/table';

type UserRow = {
    id: string;
    name: string;
    email: string;
    is_active: boolean;
    role: string | null;
    role_label: string | null;
    last_login_at: string | null;
};

type Stats = {
    total: number;
    active: number;
    inactive: number;
    loggedIn: number;
};

type Props = {
    users: Paginated<UserRow>;
    table: TableState;
    roles: SelectOption[];
    assignableRoles: SelectOption[];
    stats: Stats;
};

type UserResource = {
    user: {
        id: string;
        name: string;
        email: string;
        phone: string | null;
        is_active: boolean;
        role: string | null;
    };
    hasProfile: boolean;
    can: UserAbilities;
};

type Modal = { mode: 'create' | 'edit'; id?: string } | null;

const PER_PAGE_OPTIONS = ['10', '15', '25', '50'];

function formatDate(value: string | null): string {
    return value === null
        ? 'Belum pernah'
        : new Date(value).toLocaleString('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          });
}

export default function UserIndex({
    users,
    table,
    roles,
    assignableRoles,
    stats,
}: Props) {
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/system/users', table);

    const [modal, setModal] = useState<Modal>(null);
    const [deletingId, setDeletingId] = useState<string | null>(null);

    const editUrl =
        modal?.mode === 'edit' && modal.id
            ? `/system/users/${modal.id}/edit`
            : null;
    const manage = useResource<UserResource>(editUrl);

    const close = () => setModal(null);

    const columns: Column<UserRow>[] = [
        {
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {users.from !== null ? users.from + index : index + 1}
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
                    onClick={() => setModal({ mode: 'edit', id: row.id })}
                    className="grid gap-0.5 text-left underline-offset-4 hover:underline"
                >
                    <span className="font-medium">{row.name}</span>
                    <span className="text-xs text-muted-foreground">
                        {row.email}
                    </span>
                </button>
            ),
        },
        {
            key: 'role',
            header: 'Role',
            cell: (row) =>
                row.role_label ? (
                    <Badge variant="secondary">{row.role_label}</Badge>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        Tanpa role
                    </span>
                ),
        },
        {
            key: 'is_active',
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
            key: 'last_login_at',
            header: 'Login Terakhir',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-sm text-muted-foreground">
                    {formatDate(row.last_login_at)}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            className: 'w-20',
            cell: (row) => (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setModal({ mode: 'edit', id: row.id })}
                >
                    Kelola
                </Button>
            ),
        },
    ];

    return (
        <AppLayout
            title="Master Data - Pengguna"
            headTitle="Pengguna"
            description="Kelola akun dan akses pengguna sistem."
            actions={
                <Button onClick={() => setModal({ mode: 'create' })}>
                    <Plus className="size-4" aria-hidden="true" />
                    Tambah Pengguna
                </Button>
            }
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={Users}
                        tone="blue"
                        label="Total Pengguna"
                        value={stats.total}
                        hint="Semua akun"
                    />
                    <StatCard
                        icon={CircleCheck}
                        tone="green"
                        label="Aktif"
                        value={stats.active}
                        hint="Bisa login"
                    />
                    <StatCard
                        icon={CirclePause}
                        tone="amber"
                        label="Nonaktif"
                        value={stats.inactive}
                        hint="Diblokir"
                    />
                    <StatCard
                        icon={LogIn}
                        tone="violet"
                        label="Pernah Login"
                        value={stats.loggedIn}
                        hint="Sudah mengaktifkan akun"
                    />
                </div>

                <Card>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Select
                                    value={String(users.per_page)}
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
                                    value={table.filters.role ?? 'all'}
                                    onValueChange={(value) =>
                                        filterBy('role', value)
                                    }
                                >
                                    <SelectTrigger
                                        className="sm:w-44"
                                        aria-label="Filter role"
                                    >
                                        <SelectValue placeholder="Semua role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            Semua role
                                        </SelectItem>
                                        {roles.map((option) => (
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
                                    value={table.filters.status ?? 'all'}
                                    onValueChange={(value) =>
                                        filterBy('status', value)
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
                                        placeholder="Cari nama atau email..."
                                        className="pl-9"
                                        aria-label="Cari pengguna"
                                    />
                                </div>
                            </div>
                        </div>

                        <DataTable
                            columns={columns}
                            page={users}
                            state={table}
                            onSort={sortBy}
                            rowKey={(row) => row.id}
                            emptyMessage="Belum ada pengguna."
                        />

                        <TablePager page={users} onPageChange={goToPage} />
                    </CardContent>
                </Card>
            </div>

            <FormModal<UserValues>
                open={modal?.mode === 'create'}
                onOpenChange={(open) => !open && close()}
                title="Tambah Pengguna"
                description="Buat akun staf baru"
                action="/system/users"
                method="post"
                initialData={{
                    name: '',
                    email: '',
                    phone: '',
                    is_active: true,
                    role: '',
                    password: '',
                    password_confirmation: '',
                }}
                submitLabel="Simpan"
            >
                {(fields) => (
                    <UserFields
                        {...fields}
                        roles={assignableRoles}
                        showRole
                        showPassword
                    />
                )}
            </FormModal>

            {manage.data ? (
                <FormModal<UserValues>
                    open={modal?.mode === 'edit'}
                    onOpenChange={(open) => !open && close()}
                    title={`Kelola — ${manage.data.user.name}`}
                    description={manage.data.user.email}
                    action={`/system/users/${manage.data.user.id}`}
                    method="put"
                    initialData={{
                        name: manage.data.user.name,
                        email: manage.data.user.email,
                        phone: manage.data.user.phone ?? '',
                        is_active: manage.data.user.is_active,
                        role: manage.data.hasProfile
                            ? ''
                            : (manage.data.user.role ?? ''),
                        password: '',
                        password_confirmation: '',
                    }}
                    submitLabel="Simpan Perubahan"
                >
                    {(fields) => (
                        <>
                            <UserFields
                                {...fields}
                                roles={assignableRoles}
                                showRole={!manage.data!.hasProfile}
                                showPassword={false}
                            />
                            <UserModerationActions
                                userId={manage.data!.user.id}
                                isActive={manage.data!.user.is_active}
                                can={manage.data!.can}
                                onDelete={() =>
                                    setDeletingId(manage.data!.user.id)
                                }
                            />
                        </>
                    )}
                </FormModal>
            ) : null}

            <DeleteDialog
                open={deletingId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeletingId(null);
                        close();
                    }
                }}
                action={deletingId ? `/system/users/${deletingId}` : ''}
                title="Hapus pengguna ini?"
                description="Akun ini akan dihapus. Riwayat aktivitasnya tetap tersimpan."
            />
        </AppLayout>
    );
}
