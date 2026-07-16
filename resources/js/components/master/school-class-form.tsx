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
import type { SelectOption } from '@/types/table';

export type SchoolClassValues = {
    id?: string;
    department_id: string;
    name: string;
};

type Props = {
    action: string;
    method: 'post' | 'put';
    departments: SelectOption[];
    values?: SchoolClassValues;
    submitLabel: string;
};

export function SchoolClassForm({
    action,
    method,
    departments,
    values,
    submitLabel,
}: Props) {
    // Radix Select is not a native input, so its value is mirrored into a hidden
    // field for the form post.
    const [departmentId, setDepartmentId] = useState(
        values?.department_id ?? '',
    );

    return (
        <Form action={action} method={method} className="max-w-2xl">
            {({ processing, errors }) => (
                <Card>
                    <CardContent className="grid gap-4">
                        <Field
                            id="department_id"
                            label="Jurusan"
                            error={errors.department_id}
                            required
                        >
                            <input
                                type="hidden"
                                name="department_id"
                                value={departmentId}
                            />
                            <Select
                                value={departmentId}
                                onValueChange={setDepartmentId}
                            >
                                <SelectTrigger
                                    id="department_id"
                                    aria-invalid={Boolean(errors.department_id)}
                                >
                                    <SelectValue placeholder="Pilih jurusan" />
                                </SelectTrigger>
                                <SelectContent>
                                    {departments.map((option) => (
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
                            label="Nama Kelas"
                            error={errors.name}
                            required
                            hint="Nama harus unik dalam satu jurusan, misalnya XII A."
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
                                <Link href="/master/classes">Batal</Link>
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
