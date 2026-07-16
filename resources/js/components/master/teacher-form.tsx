import { Form, Link } from '@inertiajs/react';
import { Info, LoaderCircle } from 'lucide-react';
import { Field } from '@/components/shared/field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export type TeacherValues = {
    id?: string;
    nip: string;
    name: string;
    phone: string | null;
    user?: { email: string };
};

type Props = {
    action: string;
    method: 'post' | 'put';
    values?: TeacherValues;
    submitLabel: string;
};

export function TeacherForm({ action, method, values, submitLabel }: Props) {
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
                                    Akun login guru dibuat otomatis dari email
                                    ini. Kata sandi diacak dan tidak ditampilkan
                                    — minta guru menggunakan menu "Lupa kata
                                    sandi" untuk mengaktifkan akunnya.
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        <Field id="nip" label="NIP" error={errors.nip} required>
                            <Input
                                id="nip"
                                name="nip"
                                defaultValue={values?.nip}
                                autoFocus
                                required
                                maxLength={30}
                                inputMode="numeric"
                                aria-invalid={Boolean(errors.nip)}
                                aria-describedby={
                                    errors.nip ? 'nip-error' : undefined
                                }
                            />
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
                                required
                                maxLength={255}
                                aria-invalid={Boolean(errors.name)}
                                aria-describedby={
                                    errors.name ? 'name-error' : undefined
                                }
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
                                aria-describedby={
                                    errors.email ? 'email-error' : undefined
                                }
                            />
                        </Field>

                        <Field id="phone" label="No HP" error={errors.phone}>
                            <Input
                                id="phone"
                                name="phone"
                                type="tel"
                                defaultValue={values?.phone ?? ''}
                                maxLength={20}
                                inputMode="tel"
                                aria-invalid={Boolean(errors.phone)}
                                aria-describedby={
                                    errors.phone ? 'phone-error' : undefined
                                }
                            />
                        </Field>

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button variant="outline" asChild>
                                <Link href="/master/teachers">Batal</Link>
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
