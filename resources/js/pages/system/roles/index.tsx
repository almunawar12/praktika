import { Form, Link } from '@inertiajs/react';
import { LoaderCircle, Plus } from 'lucide-react';
import { useState } from 'react';
import { Field } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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

    return (
        <AppLayout
            title="Role & Hak Akses"
            description="Peran bawaan bersifat tetap; tambahkan peran khusus bila perlu"
        >
            <div className="flex flex-col gap-4">
                {can.create ? (
                    <div className="flex justify-end">
                        <Dialog open={open} onOpenChange={setOpen}>
                            <DialogTrigger asChild>
                                <Button>
                                    <Plus
                                        className="size-4"
                                        aria-hidden="true"
                                    />
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
                    </div>
                ) : null}

                <div className="rounded-md border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Role</TableHead>
                                <TableHead className="hidden sm:table-cell">
                                    Hak Akses
                                </TableHead>
                                <TableHead className="hidden sm:table-cell">
                                    Pengguna
                                </TableHead>
                                <TableHead className="w-16" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {roles.map((role) => (
                                <TableRow key={role.id}>
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
                                                {role.is_built_in
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
            </div>
        </AppLayout>
    );
}
