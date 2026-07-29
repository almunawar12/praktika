import { Field } from '@/components/shared/field';
import type { FieldsProps } from '@/components/shared/form-modal';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

export type DepartmentValues = {
    code: string;
    name: string;
    is_active: boolean;
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

            <div className="flex items-center gap-3 rounded-lg border p-3">
                <Switch
                    id="is_active"
                    checked={data.is_active}
                    onCheckedChange={(checked) => setData('is_active', checked)}
                />
                <div className="grid gap-0.5">
                    <Label htmlFor="is_active">Jurusan aktif</Label>
                    <p className="text-xs text-muted-foreground">
                        Jurusan nonaktif tetap tersimpan namun disembunyikan
                        dari pemilihan kelas dan siswa baru.
                    </p>
                </div>
            </div>
        </>
    );
}
