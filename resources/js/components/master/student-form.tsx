import { Info } from 'lucide-react';
import { Combobox } from '@/components/shared/combobox';
import { Field } from '@/components/shared/field';
import type { FieldsProps } from '@/components/shared/form-modal';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Input } from '@/components/ui/input';
import type { SelectOption } from '@/types/table';

export type StudentValues = {
    nis: string;
    nisn: string;
    name: string;
    email: string;
    phone: string;
    class_id: string;
};

type Props = FieldsProps<StudentValues> & {
    classes: SelectOption[];
    mode: 'create' | 'edit';
};

export function StudentFields({ data, setData, errors, classes, mode }: Props) {
    return (
        <>
            {mode === 'create' ? (
                <Alert>
                    <Info className="size-4" aria-hidden="true" />
                    <AlertDescription>
                        Akun login siswa dibuat otomatis dari email ini. Kata
                        sandi diacak dan tidak ditampilkan — minta siswa
                        menggunakan menu "Lupa kata sandi" untuk mengaktifkan
                        akunnya.
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-4 sm:grid-cols-2">
                <Field id="nis" label="NIS" error={errors.nis} required>
                    <Input
                        id="nis"
                        value={data.nis}
                        onChange={(event) => setData('nis', event.target.value)}
                        autoFocus
                        required
                        maxLength={30}
                        inputMode="numeric"
                        aria-invalid={Boolean(errors.nis)}
                    />
                </Field>

                <Field id="nisn" label="NISN" error={errors.nisn} required>
                    <Input
                        id="nisn"
                        value={data.nisn}
                        onChange={(event) => setData('nisn', event.target.value)}
                        required
                        maxLength={30}
                        inputMode="numeric"
                        aria-invalid={Boolean(errors.nisn)}
                    />
                </Field>
            </div>

            <Field id="name" label="Nama" error={errors.name} required>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    required
                    maxLength={255}
                    aria-invalid={Boolean(errors.name)}
                />
            </Field>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    id="email"
                    label="Email"
                    error={errors.email}
                    required
                    hint="Digunakan sebagai identitas login."
                >
                    <Input
                        id="email"
                        type="email"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                        required
                        maxLength={255}
                        aria-invalid={Boolean(errors.email)}
                    />
                </Field>

                <Field id="phone" label="No HP" error={errors.phone}>
                    <Input
                        id="phone"
                        type="tel"
                        inputMode="tel"
                        value={data.phone}
                        onChange={(event) =>
                            setData('phone', event.target.value)
                        }
                        maxLength={20}
                        aria-invalid={Boolean(errors.phone)}
                    />
                </Field>
            </div>

            <Field id="class_id" label="Kelas" error={errors.class_id} required>
                <Combobox
                    id="class_id"
                    value={data.class_id}
                    onValueChange={(value) => setData('class_id', value)}
                    options={classes}
                    placeholder="Pilih kelas"
                    searchPlaceholder="Cari kelas..."
                    aria-invalid={Boolean(errors.class_id)}
                />
            </Field>
        </>
    );
}
