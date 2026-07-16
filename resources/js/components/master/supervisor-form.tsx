import { Form, Link } from '@inertiajs/react';
import { Info, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { Field } from '@/components/shared/field';
import { Alert, AlertDescription } from '@/components/ui/alert';
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
import type { SelectOption } from '@/types/table';

export type SupervisorValues = {
    id?: string;
    company_id: string;
    name: string;
    position: string | null;
    phone: string | null;
    user?: { email: string };
};

type Props = {
    action: string;
    method: 'post' | 'put';
    companies: SelectOption[];
    values?: SupervisorValues;
    submitLabel: string;
};

export function SupervisorForm({
    action,
    method,
    companies,
    values,
    submitLabel,
}: Props) {
    const [companyId, setCompanyId] = useState(values?.company_id ?? '');
    const isCreate = method === 'post';

    return (
        <Form action={action} method={method} className="max-w-2xl">
            {({ processing, errors }) => (
                <Card>
                    <CardContent className="grid gap-4">
                        {isCreate ? (
                            <Alert>
                                <Info className="size-4" aria-hidden="true" />
                                <AlertDescription>
                                    Akun login dibuat otomatis dari email ini.
                                    Kata sandi diacak dan tidak ditampilkan —
                                    minta pembimbing menggunakan menu "Lupa kata
                                    sandi" untuk mengaktifkannya. Pembimbing
                                    hanya dapat melihat siswa PKL di
                                    perusahaannya.
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        <Field
                            id="company_id"
                            label="Perusahaan"
                            error={errors.company_id}
                            required
                        >
                            {/* Radix Select is not a native input; its value is mirrored
                                into a hidden field for the post. */}
                            <input
                                type="hidden"
                                name="company_id"
                                value={companyId}
                            />
                            <Select
                                value={companyId}
                                onValueChange={setCompanyId}
                            >
                                <SelectTrigger
                                    id="company_id"
                                    aria-invalid={Boolean(errors.company_id)}
                                >
                                    <SelectValue placeholder="Pilih perusahaan" />
                                </SelectTrigger>
                                <SelectContent>
                                    {companies.map((option) => (
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
                                autoFocus
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
                            hint="Digunakan sebagai identitas login."
                        >
                            <Input
                                id="email"
                                name="email"
                                type="email"
                                defaultValue={values?.user?.email}
                                required
                                maxLength={255}
                                aria-invalid={Boolean(errors.email)}
                            />
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="position"
                                label="Jabatan"
                                error={errors.position}
                            >
                                <Input
                                    id="position"
                                    name="position"
                                    defaultValue={values?.position ?? ''}
                                    maxLength={255}
                                    placeholder="HRD, Supervisor Produksi, ..."
                                    aria-invalid={Boolean(errors.position)}
                                />
                            </Field>

                            <Field
                                id="phone"
                                label="No HP"
                                error={errors.phone}
                            >
                                <Input
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    inputMode="tel"
                                    defaultValue={values?.phone ?? ''}
                                    maxLength={20}
                                    aria-invalid={Boolean(errors.phone)}
                                />
                            </Field>
                        </div>

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button variant="outline" asChild>
                                <Link href="/master/supervisors">Batal</Link>
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
