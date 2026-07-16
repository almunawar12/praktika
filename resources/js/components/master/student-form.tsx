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

export type StudentValues = {
    id?: string;
    nis: string;
    nisn: string;
    name: string;
    phone: string | null;
    class_id: string;
    user?: { email: string };
};

type Props = {
    action: string;
    method: 'post' | 'put';
    classes: SelectOption[];
    values?: StudentValues;
    submitLabel: string;
};

export function StudentForm({
    action,
    method,
    classes,
    values,
    submitLabel,
}: Props) {
    const [classId, setClassId] = useState(values?.class_id ?? '');
    const isCreate = method === 'post';

    return (
        <Form action={action} method={method} className="max-w-3xl">
            {({ processing, errors }) => (
                <Card>
                    <CardContent className="grid gap-4">
                        {isCreate ? (
                            <Alert>
                                <Info className="size-4" aria-hidden="true" />
                                <AlertDescription>
                                    Akun login siswa dibuat otomatis dari email
                                    ini. Kata sandi diacak dan tidak ditampilkan
                                    — minta siswa menggunakan menu "Lupa kata
                                    sandi" untuk mengaktifkan akunnya.
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="nis"
                                label="NIS"
                                error={errors.nis}
                                required
                            >
                                <Input
                                    id="nis"
                                    name="nis"
                                    defaultValue={values?.nis}
                                    autoFocus
                                    required
                                    maxLength={30}
                                    inputMode="numeric"
                                    aria-invalid={Boolean(errors.nis)}
                                    aria-describedby={
                                        errors.nis ? 'nis-error' : undefined
                                    }
                                />
                            </Field>

                            <Field
                                id="nisn"
                                label="NISN"
                                error={errors.nisn}
                                required
                            >
                                <Input
                                    id="nisn"
                                    name="nisn"
                                    defaultValue={values?.nisn}
                                    required
                                    maxLength={30}
                                    inputMode="numeric"
                                    aria-invalid={Boolean(errors.nisn)}
                                    aria-describedby={
                                        errors.nisn ? 'nisn-error' : undefined
                                    }
                                />
                            </Field>
                        </div>

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
                                aria-describedby={
                                    errors.name ? 'name-error' : undefined
                                }
                            />
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
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
                                    aria-describedby={
                                        errors.email ? 'email-error' : undefined
                                    }
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
                                    aria-describedby={
                                        errors.phone ? 'phone-error' : undefined
                                    }
                                />
                            </Field>
                        </div>

                        <Field
                            id="class_id"
                            label="Kelas"
                            error={errors.class_id}
                            required
                        >
                            <input
                                type="hidden"
                                name="class_id"
                                value={classId}
                            />
                            <Select value={classId} onValueChange={setClassId}>
                                <SelectTrigger
                                    id="class_id"
                                    aria-invalid={Boolean(errors.class_id)}
                                >
                                    <SelectValue placeholder="Pilih kelas" />
                                </SelectTrigger>
                                <SelectContent>
                                    {classes.map((option) => (
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

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button variant="outline" asChild>
                                <Link href="/master/students">Batal</Link>
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
