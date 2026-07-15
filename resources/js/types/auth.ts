export type RoleName =
    | 'super-admin'
    | 'admin-pkl'
    | 'guru-pembimbing'
    | 'pembimbing-industri'
    | 'siswa';

/**
 * Mirrors HandleInertiaRequests::userPayload() — deliberately narrower than the
 * User model, so adding a column to `users` never leaks it into the page payload.
 */
export type User = {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    avatar: string | null;
    role: RoleName | null;
    roleLabel: string | null;
};

export type Auth = {
    user: User | null;
    permissions: string[];
};
