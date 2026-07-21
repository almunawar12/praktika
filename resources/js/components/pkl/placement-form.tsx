import { Info } from 'lucide-react';
import { Combobox } from '@/components/shared/combobox';
import { Field } from '@/components/shared/field';
import type { FieldsProps } from '@/components/shared/form-modal';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Input } from '@/components/ui/input';
import type { SelectOption } from '@/types/table';

export type PlacementValues = {
    student_id: string;
    company_id: string;
    teacher_id: string;
    start_date: string;
    end_date: string;
};

export type PlacementFormOptions = {
    students: SelectOption[];
    companies: SelectOption[];
    teachers: SelectOption[];
};

type Props = FieldsProps<PlacementValues> & {
    options: PlacementFormOptions;
    mode: 'create' | 'edit';
};

function SelectField({
    id,
    label,
    options,
    value,
    onChange,
    error,
    placeholder,
}: {
    id: keyof PlacementValues;
    label: string;
    options: SelectOption[];
    value: string;
    onChange: (value: string) => void;
    error?: string;
    placeholder: string;
}) {
    return (
        <Field id={id} label={label} error={error} required>
            <Combobox
                id={id}
                value={value}
                onValueChange={onChange}
                options={options}
                placeholder={placeholder}
                searchPlaceholder={`${placeholder}...`}
                aria-invalid={Boolean(error)}
            />
        </Field>
    );
}

export function PlacementFields({
    data,
    setData,
    errors,
    options,
    mode,
}: Props) {
    return (
        <>
            {mode === 'create' ? (
                <Alert>
                    <Info className="size-4" aria-hidden="true" />
                    <AlertDescription>
                        Penempatan dibuat sebagai <strong>draft</strong>. Kuota
                        perusahaan dan aturan satu PKL aktif per siswa baru
                        diperiksa saat penempatan diaktifkan.
                    </AlertDescription>
                </Alert>
            ) : null}

            <SelectField
                id="student_id"
                label="Siswa"
                options={options.students}
                value={data.student_id}
                onChange={(value) => setData('student_id', value)}
                error={errors.student_id}
                placeholder="Pilih siswa"
            />

            <SelectField
                id="company_id"
                label="Perusahaan"
                options={options.companies}
                value={data.company_id}
                onChange={(value) => setData('company_id', value)}
                error={errors.company_id}
                placeholder="Pilih perusahaan"
            />

            <SelectField
                id="teacher_id"
                label="Guru Pembimbing"
                options={options.teachers}
                value={data.teacher_id}
                onChange={(value) => setData('teacher_id', value)}
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
                        type="date"
                        value={data.start_date}
                        onChange={(event) =>
                            setData('start_date', event.target.value)
                        }
                        required
                        aria-invalid={Boolean(errors.start_date)}
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
                        type="date"
                        value={data.end_date}
                        onChange={(event) =>
                            setData('end_date', event.target.value)
                        }
                        required
                        aria-invalid={Boolean(errors.end_date)}
                    />
                </Field>
            </div>
        </>
    );
}
