import { router } from '@inertiajs/react';
import { KeyRound, Power, UserCog } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

export type UserAbilities = {
    delete: boolean;
    toggleStatus: boolean;
    resetPassword: boolean;
    impersonate: boolean;
};

type Props = {
    userId: string;
    isActive: boolean;
    can: UserAbilities;
    onDelete: () => void;
};

/**
 * Account actions that are not part of the profile form — each is its own
 * permission, rule and audit entry on the server, so they stay separate from the
 * "save" of the edit form.
 */
export function UserModerationActions({
    userId,
    isActive,
    can,
    onDelete,
}: Props) {
    const [busy, setBusy] = useState(false);

    const act = (method: 'post' | 'put', url: string) => {
        setBusy(true);
        router[method](
            url,
            {},
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };

    if (
        !can.toggleStatus &&
        !can.resetPassword &&
        !can.impersonate &&
        !can.delete
    ) {
        return null;
    }

    return (
        <div className="grid gap-2 rounded-lg border p-3">
            <p className="text-sm font-medium">Tindakan Akun</p>

            {can.toggleStatus ? (
                <Button
                    type="button"
                    variant="outline"
                    disabled={busy}
                    onClick={() => act('put', `/system/users/${userId}/status`)}
                >
                    <Power className="size-4" aria-hidden="true" />
                    {isActive ? 'Nonaktifkan Akun' : 'Aktifkan Akun'}
                </Button>
            ) : null}

            {can.resetPassword ? (
                <Button
                    type="button"
                    variant="outline"
                    disabled={busy}
                    onClick={() =>
                        act('put', `/system/users/${userId}/reset-password`)
                    }
                >
                    <KeyRound className="size-4" aria-hidden="true" />
                    Reset Kata Sandi
                </Button>
            ) : null}

            {can.impersonate ? (
                <Button
                    type="button"
                    variant="outline"
                    disabled={busy}
                    onClick={() =>
                        act('post', `/system/users/${userId}/impersonate`)
                    }
                >
                    <UserCog className="size-4" aria-hidden="true" />
                    Masuk sebagai Pengguna
                </Button>
            ) : null}

            {can.delete ? (
                <Button
                    type="button"
                    variant="outline"
                    className="text-destructive hover:text-destructive"
                    onClick={onDelete}
                >
                    Hapus Pengguna
                </Button>
            ) : null}
        </div>
    );
}
