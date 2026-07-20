import { Field } from '@/components/shared/field';
import type { FieldsProps } from '@/components/shared/form-modal';
import { Input } from '@/components/ui/input';

export type DepartmentValues = {
    code: string;
    name: string;
};

export function DepartmentFields({
    data,
    setData,
    errors,
}: FieldsProps<DepartmentValues>) {
    return (
        <>
            <Field id="code" label="Kode" error={errors.code} required>
                <Input
                    id="code"
                    value={data.code}
                    onChange={(event) => setData('code', event.target.value)}
                    autoFocus
                    required
                    maxLength={20}
                    aria-invalid={Boolean(errors.code)}
                />
            </Field>

            <Field id="name" label="Nama Jurusan" error={errors.name} required>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    required
                    maxLength={255}
                    aria-invalid={Boolean(errors.name)}
                />
            </Field>
        </>
    );
}
