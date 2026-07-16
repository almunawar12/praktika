import { router } from '@inertiajs/react';
import { KeyRound, Power, Trash2, UserCog } from 'lucide-react';
import { useState } from 'react';
import { DeleteDialog } from '@/components/shared/delete-dialog';
import { UserForm } from '@/components/system/user-form';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { SelectOption } from '@/types/table';

type Props = {
    user: {
        id: string;
        name: string;
        email: string;
        phone: string | null;
        is_active: boolean;
        role: string | null;
    };
    roles: SelectOption[];
    hasProfile: boolean;
    can: {
        delete: boolean;
        toggleStatus: boolean;
        resetPassword: boolean;
        impersonate: boolean;
    };
};

export default function UserEdit({ user, roles, hasProfile, can }: Props) {
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [busy, setBusy] = useState(false);

    const act = (method: 'post' | 'put', url: string) => {
        setBusy(true);
        router[method](
            url,
            {},
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };

    return (
        <AppLayout title={`Kelola — ${user.name}`} description={user.email}>
            <div className="grid gap-4 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <UserForm
                        action={`/system/users/${user.id}`}
                        method="put"
                        roles={roles}
                        values={{
                            name: user.name,
                            email: user.email,
                            phone: user.phone,
                            is_active: user.is_active,
                            role: user.role,
                        }}
                        // A profile-backed account keeps the role its module assigned.
                        showRole={!hasProfile}
                        showPassword={false}
                        submitLabel="Simpan Perubahan"
                    />
                </div>

                <Card className="h-fit">
                    <CardHeader>
                        <CardTitle>Tindakan</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        {can.toggleStatus ? (
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={() =>
                                    act(
                                        'put',
                                        `/system/users/${user.id}/status`,
                                    )
                                }
                            >
                                <Power className="size-4" aria-hidden="true" />
                                {user.is_active
                                    ? 'Nonaktifkan Akun'
                                    : 'Aktifkan Akun'}
                            </Button>
                        ) : null}

                        {can.resetPassword ? (
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={() =>
                                    act(
                                        'put',
                                        `/system/users/${user.id}/reset-password`,
                                    )
                                }
                            >
                                <KeyRound
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Reset Kata Sandi
                            </Button>
                        ) : null}

                        {can.impersonate ? (
                            <Button
                                variant="outline"
                                disabled={busy}
                                onClick={() =>
                                    act(
                                        'post',
                                        `/system/users/${user.id}/impersonate`,
                                    )
                                }
                            >
                                <UserCog
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                Masuk sebagai Pengguna
                            </Button>
                        ) : null}

                        {can.delete ? (
                            <Button
                                variant="outline"
                                className="text-destructive hover:text-destructive"
                                onClick={() => setConfirmDelete(true)}
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                                Hapus Pengguna
                            </Button>
                        ) : null}
                    </CardContent>
                </Card>
            </div>

            <DeleteDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                action={`/system/users/${user.id}`}
                title="Hapus pengguna ini?"
                description={`Akun ${user.name} akan dihapus. Riwayat aktivitasnya tetap tersimpan.`}
            />
        </AppLayout>
    );
}
