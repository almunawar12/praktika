import { usePage } from '@inertiajs/react';

/**
 * Permission check for the *current* user, mirroring the server-side gate.
 *
 * This drives what the UI offers, not what the user is allowed to do — every route
 * is independently protected by `can:` middleware and a policy. Hiding a button is
 * never the security boundary.
 */
export function usePermission() {
    const permissions = usePage().props.auth.permissions;

    const can = (permission: string): boolean =>
        permissions.includes(permission);

    const canAny = (...names: string[]): boolean => names.some(can);

    const canAll = (...names: string[]): boolean => names.every(can);

    return { can, canAny, canAll, permissions };
}
