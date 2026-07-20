import { Field } from '@/components/shared/field';
import type { FieldsProps } from '@/components/shared/form-modal';
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
    department_id: string;
    name: string;
};

type Props = FieldsProps<SchoolClassValues> & {
    departments: SelectOption[];
};

export function SchoolClassFields({
    data,
    setData,
    errors,
    departments,
}: Props) {
    return (
        <>
            <Field
                id="department_id"
                label="Jurusan"
                error={errors.department_id}
                required
            >
                <Select
                    value={data.department_id}
                    onValueChange={(value) => setData('department_id', value)}
                >
                    <SelectTrigger
                        id="department_id"
                        aria-invalid={Boolean(errors.department_id)}
                    >
                        <SelectValue placeholder="Pilih jurusan" />
                    </SelectTrigger>
                    <SelectContent>
                        {departments.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
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
