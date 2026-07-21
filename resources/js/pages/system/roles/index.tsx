import { Form, Link } from '@inertiajs/react';
import { LoaderCircle, Lock, Plus, ShieldCheck, SquarePlus, Users } from 'lucide-react';
import { useState } from 'react';
import { Field } from '@/components/shared/field';
import { StatCard } from '@/components/shared/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';

type RoleRow = {
    id: string;
    name: string;
    label: string;
    is_built_in: boolean;
    permissions_count: number;
    users_count: number;
};

type Props = {
    roles: RoleRow[];
    can: { create: boolean };
};

export default function RoleIndex({ roles, can }: Props) {
    const [open, setOpen] = useState(false);

    const builtIn = roles.filter((role) => role.is_built_in).length;
    const totalUsers = roles.reduce((sum, role) => sum + role.users_count, 0);

    return (
        <AppLayout
            title="Role & Hak Akses"
            description="Peran bawaan bersifat tetap; tambahkan peran khusus bila perlu."
            actions={
                can.create ? (
                    <Dialog open={open} onOpenChange={setOpen}>
                        <DialogTrigger asChild>
                            <Button>
                                <Plus className="size-4" aria-hidden="true" />
                                Tambah Role
                            </Button>
                        </DialogTrigger>
                        <DialogContent>
                            <DialogHeader>
                                <DialogTitle>Role Baru</DialogTitle>
                            </DialogHeader>
                            <Form
                                action="/system/roles"
                                method="post"
                                onSuccess={() => setOpen(false)}
                            >
                                {({ processing, errors }) => (
                                    <div className="grid gap-4">
                                        <Field
                                            id="name"
                                            label="Nama Role"
                                            error={errors.name}
                                            required
                                            hint="Contoh: Kepala Sekolah. Hak akses ditetapkan setelah role dibuat."
                                        >
                                            <Input
                                                id="name"
                                                name="name"
                                                required
                                                maxLength={255}
                                                aria-invalid={Boolean(
                                                    errors.name,
                                                )}
                                            />
                                        </Field>
                                        <div className="flex justify-end">
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                            >
                                                {processing ? (
                                                    <LoaderCircle
                                                        className="size-4 animate-spin"
                                                        aria-hidden="true"
                                                    />
                                                ) : null}
                                                Buat
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </Form>
                        </DialogContent>
                    </Dialog>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={ShieldCheck}
                        tone="blue"
                        label="Total Role"
                        value={roles.length}
                        hint="Semua peran"
                    />
                    <StatCard
                        icon={Lock}
                        tone="amber"
                        label="Role Bawaan"
                        value={builtIn}
                        hint="Tidak dapat diubah"
                    />
                    <StatCard
                        icon={SquarePlus}
                        tone="green"
                        label="Role Kustom"
                        value={roles.length - builtIn}
                        hint="Dibuat manual"
                    />
                    <StatCard
                        icon={Users}
                        tone="violet"
                        label="Total Pengguna"
                        value={totalUsers}
                        hint="Terikat ke role"
                    />
                </div>

                <Card>
                    <CardContent>
                        <div className="overflow-x-auto rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">
                                            No
                                        </TableHead>
                                        <TableHead>Role</TableHead>
                                        <TableHead className="hidden sm:table-cell">
                                            Hak Akses
                                        </TableHead>
                                        <TableHead className="hidden sm:table-cell">
                                            Pengguna
                                        </TableHead>
                                        <TableHead className="w-16">
                                            Aksi
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {roles.map((role, index) => (
                                        <TableRow key={role.id}>
                                            <TableCell className="text-muted-foreground tabular-nums">
                                                {index + 1}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">
                                                        {role.label}
                                                    </span>
                                                    {role.is_built_in ? (
                                                        <Badge variant="secondary">
                                                            Bawaan
                                                        </Badge>
                                                    ) : null}
                                                </div>
                                            </TableCell>
                                            <TableCell className="hidden text-muted-foreground sm:table-cell">
                                                {role.permissions_count} izin
                                            </TableCell>
                                            <TableCell className="hidden text-muted-foreground sm:table-cell">
                                                {role.users_count}
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    asChild
                                                >
                                                    <Link
                                                        href={`/system/roles/${role.id}/edit`}
                                                    >
                                                        {role.name ===
                                                        'super-admin'
                                                            ? 'Lihat'
                                                            : 'Kelola'}
                                                    </Link>
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
