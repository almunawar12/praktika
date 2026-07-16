import { Link, router } from '@inertiajs/react';
import { LoaderCircle, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DeleteDialog } from '@/components/shared/delete-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

type Permission = { name: string; action: string };
type Group = { resource: string; permissions: Permission[] };

type Props = {
    role: {
        id: string;
        name: string;
        label: string;
        permissions: string[];
    };
    catalogue: Group[];
    editable: boolean;
    can: { delete: boolean };
};

export default function RoleEdit({ role, catalogue, editable, can }: Props) {
    const [granted, setGranted] = useState<Set<string>>(
        new Set(role.permissions),
    );
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [saving, setSaving] = useState(false);

    const toggle = (name: string) => {
        setGranted((current) => {
            const next = new Set(current);

            if (next.has(name)) {
                next.delete(name);
            } else {
                next.add(name);
            }

            return next;
        });
    };

    const toggleGroup = (group: Group, on: boolean) => {
        setGranted((current) => {
            const next = new Set(current);

            for (const permission of group.permissions) {
                if (on) {
                    next.add(permission.name);
                } else {
                    next.delete(permission.name);
                }
            }

            return next;
        });
    };

    const save = () => {
        setSaving(true);
        router.put(
            `/system/roles/${role.id}`,
            { permissions: Array.from(granted) },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    return (
        <AppLayout
            title={role.label}
            description={
                editable
                    ? 'Tetapkan hak akses role ini'
                    : 'Role bawaan — hak akses ditampilkan untuk referensi dan tidak dapat diubah'
            }
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/system/roles">Kembali</Link>
                    </Button>
                    {!editable ? (
                        <Badge variant="secondary">Hanya-baca</Badge>
                    ) : null}
                    <div className="ml-auto flex gap-2">
                        {can.delete ? (
                            <Button
                                variant="outline"
                                className="text-destructive hover:text-destructive"
                                onClick={() => setConfirmDelete(true)}
                            >
                                <Trash2 className="size-4" aria-hidden="true" />
                                Hapus
                            </Button>
                        ) : null}
                        {editable ? (
                            <Button onClick={save} disabled={saving}>
                                {saving ? (
                                    <LoaderCircle
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                ) : null}
                                Simpan
                            </Button>
                        ) : null}
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {catalogue.map((group) => {
                        const total = group.permissions.length;
                        const active = group.permissions.filter((p) =>
                            granted.has(p.name),
                        ).length;

                        return (
                            <Card key={group.resource}>
                                <CardHeader className="flex-row items-center justify-between gap-2 space-y-0">
                                    <CardTitle className="text-sm font-medium">
                                        {group.resource}
                                    </CardTitle>
                                    {editable ? (
                                        <label className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <input
                                                type="checkbox"
                                                className="size-4"
                                                checked={active === total}
                                                ref={(el) => {
                                                    if (el) {
                                                        el.indeterminate =
                                                            active > 0 &&
                                                            active < total;
                                                    }
                                                }}
                                                onChange={(e) =>
                                                    toggleGroup(
                                                        group,
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                            Semua
                                        </label>
                                    ) : (
                                        <span className="text-xs text-muted-foreground">
                                            {active}/{total}
                                        </span>
                                    )}
                                </CardHeader>
                                <CardContent className="grid gap-2">
                                    {group.permissions.map((permission) => (
                                        <label
                                            key={permission.name}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                className="size-4"
                                                checked={granted.has(
                                                    permission.name,
                                                )}
                                                disabled={!editable}
                                                onChange={() =>
                                                    toggle(permission.name)
                                                }
                                            />
                                            {permission.action}
                                        </label>
                                    ))}
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            </div>

            <DeleteDialog
                open={confirmDelete}
                onOpenChange={setConfirmDelete}
                action={`/system/roles/${role.id}`}
                title="Hapus role ini?"
                description={`Role ${role.label} akan dihapus. Pengguna yang memakainya kehilangan role ini.`}
            />
        </AppLayout>
    );
}
