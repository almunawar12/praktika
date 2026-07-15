import { usePage } from '@inertiajs/react';
import type { RoleName, User } from '@/types';

export function useAuth() {
    const auth = usePage().props.auth;

    const user: User | null = auth.user;

    const is = (...roles: RoleName[]): boolean =>
        user?.role != null && roles.includes(user.role);

    return {
        user,
        role: user?.role ?? null,
        roleLabel: user?.roleLabel ?? null,
        is,
        isSuperAdmin: is('super-admin'),
        isAdminPkl: is('admin-pkl'),
        isGuruPembimbing: is('guru-pembimbing'),
        isPembimbingIndustri: is('pembimbing-industri'),
        isSiswa: is('siswa'),
    };
}
