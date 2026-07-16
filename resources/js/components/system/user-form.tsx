import { Form, Link } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { Field } from '@/components/shared/field';
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
import { Switch } from '@/components/ui/switch';
import type { SelectOption } from '@/types/table';

export type UserFormValues = {
    name: string;
    email: string;
    phone: string | null;
    is_active: boolean;
    role: string | null;
};

type Props = {
    action: string;
    method: 'post' | 'put';
    roles: SelectOption[];
    values?: UserFormValues;
    showRole: boolean;
    showPassword: boolean;
    submitLabel: string;
};

export function UserForm({
    action,
    method,
    roles,
    values,
    showRole,
    showPassword,
    submitLabel,
}: Props) {
    const [role, setRole] = useState(values?.role ?? roles[0]?.value ?? '');
    const [active, setActive] = useState(values?.is_active ?? true);

    return (
        <Form action={action} method={method} className="max-w-2xl">
            {({ processing, errors }) => (
                <Card>
                    <CardContent className="grid gap-4">
                        <Field
                            id="name"
                            label="Nama"
                            error={errors.name}
                            required
                        >
                            <Input
                                id="name"
                                name="name"
                                defaultValue={values?.name}
                                required
                                maxLength={255}
                                aria-invalid={Boolean(errors.name)}
                            />
                        </Field>

                        <Field
                            id="email"
                            label="Email"
                            error={errors.email}
                            required
                        >
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                defaultValue={values?.email}
                                required
                                aria-invalid={Boolean(errors.email)}
                            />
                        </Field>

                        <Field id="phone" label="No HP" error={errors.phone}>
                            <Input
                                id="phone"
                                name="phone"
                                defaultValue={values?.phone ?? ''}
                                maxLength={30}
                            />
                        </Field>

                        {showRole ? (
                            <Field
                                id="role"
                                label="Role"
                                error={errors.role}
                                required
                            >
                                <input type="hidden" name="role" value={role} />
                                <Select value={role} onValueChange={setRole}>
                                    <SelectTrigger id="role">
                                        <SelectValue placeholder="Pilih role" />
                                    </SelectTrigger>
                                    <SelectContent>
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
                            </Field>
                        ) : null}

                        {showPassword ? (
                            <>
                                <Field
                                    id="password"
                                    label="Kata Sandi"
                                    error={errors.password}
                                    required
                                >
                                    <Input
                                        id="password"
                                        name="password"
                                        type="password"
                                        autoComplete="new-password"
                                        required
                                    />
                                </Field>
                                <Field
                                    id="password_confirmation"
                                    label="Konfirmasi Kata Sandi"
                                    required
                                >
                                    <Input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        required
                                    />
                                </Field>
                            </>
                        ) : null}

                        <Field id="is_active" label="Status Akun">
                            <input
                                type="hidden"
                                name="is_active"
                                value={active ? '1' : '0'}
                            />
                            <div className="flex items-center gap-3">
                                <Switch
                                    id="is_active"
                                    checked={active}
                                    onCheckedChange={setActive}
                                />
                                <span className="text-sm text-muted-foreground">
                                    {active ? 'Aktif' : 'Nonaktif'}
                                </span>
                            </div>
                        </Field>

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button variant="outline" asChild>
                                <Link href="/system/users">Batal</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? (
                                    <LoaderCircle
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                ) : null}
                                {submitLabel}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}
        </Form>
    );
}
