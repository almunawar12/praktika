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

export type PlacementValues = {
    id?: string;
    student_id: string;
    company_id: string;
    teacher_id: string;
    start_date: string;
    end_date: string;
};

type Props = {
    action: string;
    method: 'post' | 'put';
    students: SelectOption[];
    companies: SelectOption[];
    teachers: SelectOption[];
    values?: PlacementValues;
    submitLabel: string;
};

/** Radix Select is not a native input, so its value is mirrored into a hidden field. */
function SelectField({
    id,
    label,
    options,
    value,
    onChange,
    error,
    placeholder,
}: {
    id: string;
    label: string;
    options: SelectOption[];
    value: string;
    onChange: (value: string) => void;
    error?: string;
    placeholder: string;
}) {
    return (
        <Field id={id} label={label} error={error} required>
            <input type="hidden" name={id} value={value} />
            <Select value={value} onValueChange={onChange}>
                <SelectTrigger id={id} aria-invalid={Boolean(error)}>
                    <SelectValue placeholder={placeholder} />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );
}

export function PlacementForm({
    action,
    method,
    students,
    companies,
    teachers,
    values,
    submitLabel,
}: Props) {
    const [studentId, setStudentId] = useState(values?.student_id ?? '');
    const [companyId, setCompanyId] = useState(values?.company_id ?? '');
    const [teacherId, setTeacherId] = useState(values?.teacher_id ?? '');
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
                                    Penempatan dibuat sebagai{' '}
                                    <strong>draft</strong>. Kuota perusahaan dan
                                    aturan satu PKL aktif per siswa baru
                                    diperiksa saat penempatan diaktifkan.
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        <SelectField
                            id="student_id"
                            label="Siswa"
                            options={students}
                            value={studentId}
                            onChange={setStudentId}
                            error={errors.student_id}
                            placeholder="Pilih siswa"
                        />

                        <SelectField
                            id="company_id"
                            label="Perusahaan"
                            options={companies}
                            value={companyId}
                            onChange={setCompanyId}
                            error={errors.company_id}
                            placeholder="Pilih perusahaan"
                        />

                        <SelectField
                            id="teacher_id"
                            label="Guru Pembimbing"
                            options={teachers}
                            value={teacherId}
                            onChange={setTeacherId}
                            error={errors.teacher_id}
                            placeholder="Pilih guru pembimbing"
                        />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="start_date"
                                label="Tanggal Mulai"
                                error={errors.start_date}
                                required
                            >
                                <Input
                                    id="start_date"
                                    name="start_date"
                                    type="date"
                                    defaultValue={values?.start_date}
                                    required
                                    aria-invalid={Boolean(errors.start_date)}
                                    aria-describedby={
                                        errors.start_date
                                            ? 'start_date-error'
                                            : undefined
                                    }
                                />
                            </Field>

                            <Field
                                id="end_date"
                                label="Tanggal Selesai"
                                error={errors.end_date}
                                required
                            >
                                <Input
                                    id="end_date"
                                    name="end_date"
                                    type="date"
                                    defaultValue={values?.end_date}
                                    required
                                    aria-invalid={Boolean(errors.end_date)}
                                    aria-describedby={
                                        errors.end_date
                                            ? 'end_date-error'
                                            : undefined
                                    }
                                />
                            </Field>
                        </div>

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button variant="outline" asChild>
                                <Link href="/pkl/placements">Batal</Link>
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
