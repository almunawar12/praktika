import { Form, Link } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { Field } from '@/components/shared/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export type DepartmentValues = {
    id?: string;
    code: string;
    name: string;
};

type Props = {
    action: string;
    method: 'post' | 'put';
    values?: DepartmentValues;
    submitLabel: string;
};

export function DepartmentForm({ action, method, values, submitLabel }: Props) {
    return (
        <Form action={action} method={method} className="max-w-2xl">
            {({ processing, errors }) => (
                <Card>
                    <CardContent className="grid gap-4">
                        <Field
                            id="code"
                            label="Kode"
                            error={errors.code}
                            required
                        >
                            <Input
                                id="code"
                                name="code"
                                defaultValue={values?.code}
                                autoFocus
                                required
                                maxLength={20}
                                aria-invalid={Boolean(errors.code)}
                                aria-describedby={
                                    errors.code ? 'code-error' : undefined
                                }
                            />
                        </Field>

                        <Field
                            id="name"
                            label="Nama Jurusan"
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

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button variant="outline" asChild>
                                <Link href="/master/departments">Batal</Link>
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
