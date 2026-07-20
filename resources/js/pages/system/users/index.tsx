import { Plus } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import { UserFields, type UserValues } from '@/components/system/user-form';
import {
    UserModerationActions,
    type UserAbilities,
} from '@/components/system/user-moderation-actions';
import { DeleteDialog } from '@/components/shared/delete-dialog';
import { FormModal } from '@/components/shared/form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

type Props = {
    users: Paginated<UserRow>;
    table: TableState;
    roles: SelectOption[];
    assignableRoles: SelectOption[];
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
                    <Badge className="border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400">
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
            header: '',
            className: 'w-16',
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
        <AppLayout title="Pengguna" description="Kelola akun dan akses">
            <div className="flex flex-col gap-4">
                <div className="flex justify-end">
                    <Button onClick={() => setModal({ mode: 'create' })}>
                        <Plus className="size-4" aria-hidden="true" />
                        Tambah Pengguna
                    </Button>
                </div>

                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari nama atau email..."
                    filters={[
                        {
                            key: 'role',
                            label: 'Role',
                            value: table.filters.role ?? 'all',
                            options: roles,
                        },
                        {
                            key: 'status',
                            label: 'Status',
                            value: table.filters.status ?? 'all',
                            options: [
                                { value: 'active', label: 'Aktif' },
                                { value: 'inactive', label: 'Nonaktif' },
                            ],
                        },
                    ]}
                    onFilterChange={filterBy}
                />

                <DataTable
                    columns={columns}
                    page={users}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada pengguna."
                />

                <DataTablePagination
                    page={users}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
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
                        // A profile-backed account keeps the role its module assigned,
                        // so the picker is hidden and no role is submitted.
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
