import { Field } from '@/components/shared/field';
import type { FieldsProps } from '@/components/shared/form-modal';
import { Input } from '@/components/ui/input';

export type AcademicYearValues = {
    name: string;
    start_date: string;
    end_date: string;
};

export function AcademicYearFields({
    data,
    setData,
    errors,
}: FieldsProps<AcademicYearValues>) {
    return (
        <>
            <Field
                id="name"
                label="Nama"
                error={errors.name}
                required
                hint='Contoh: "2026/2027".'
            >
                <Input
                    id="name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    autoFocus
                    required
                    maxLength={255}
                    aria-invalid={Boolean(errors.name)}
                />
            </Field>

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
