import { Field } from '@/components/shared/field';
import type { FieldsProps } from '@/components/shared/form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import type { SelectOption } from '@/types/table';

export type SchoolClassValues = {
    department_id: string;
    academic_year_id: string;
    name: string;
    is_active: boolean;
};

type Props = FieldsProps<SchoolClassValues> & {
    departments: SelectOption[];
    academicYears: SelectOption[];
};

export function SchoolClassFields({
    data,
    setData,
    errors,
    departments,
    academicYears,
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
                id="academic_year_id"
                label="Tahun Ajaran"
                error={errors.academic_year_id}
            >
                <Select
                    value={data.academic_year_id}
                    onValueChange={(value) =>
                        setData('academic_year_id', value)
                    }
                >
                    <SelectTrigger
                        id="academic_year_id"
                        aria-invalid={Boolean(errors.academic_year_id)}
                    >
                        <SelectValue placeholder="Otomatis (tahun ajaran aktif)" />
                    </SelectTrigger>
                    <SelectContent>
                        {academicYears.map((option) => (
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

            <div className="flex items-center gap-3 rounded-lg border p-3">
                <Switch
                    id="is_active"
                    checked={data.is_active}
                    onCheckedChange={(checked) => setData('is_active', checked)}
                />
                <div className="grid gap-0.5">
                    <Label htmlFor="is_active">Kelas aktif</Label>
                    <p className="text-xs text-muted-foreground">
                        Kelas nonaktif tetap tersimpan namun disembunyikan dari
                        penempatan siswa baru.
                    </p>
                </div>
            </div>
        </>
    );
}
