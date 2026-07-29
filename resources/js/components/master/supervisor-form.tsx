import { Info } from 'lucide-react';
import { Combobox } from '@/components/shared/combobox';
import { Field } from '@/components/shared/field';
import type { FieldsProps } from '@/components/shared/form-modal';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Input } from '@/components/ui/input';
import type { SelectOption } from '@/types/table';

export type SupervisorValues = {
    company_id: string;
    name: string;
    email: string;
    position: string;
    phone: string;
};

type Props = FieldsProps<SupervisorValues> & {
    companies: SelectOption[];
    mode: 'create' | 'edit';
};

export function SupervisorFields({
    data,
    setData,
    errors,
    companies,
    mode,
}: Props) {
    return (
        <>
            {mode === 'create' ? (
                <Alert>
                    <Info className="size-4" aria-hidden="true" />
                    <AlertDescription>
                        Akun login dibuat otomatis dari email ini. Kata sandi
                        diacak dan tidak ditampilkan — minta pembimbing
                        menggunakan menu "Lupa kata sandi" untuk
                        mengaktifkannya. Pembimbing hanya dapat melihat siswa
                        PKL di perusahaannya.
                    </AlertDescription>
                </Alert>
            ) : null}

            <Field
                id="company_id"
                label="Perusahaan"
                error={errors.company_id}
                required
            >
                <Combobox
                    id="company_id"
                    value={data.company_id}
                    onValueChange={(value) => setData('company_id', value)}
                    options={companies}
                    placeholder="Pilih perusahaan"
                    searchPlaceholder="Cari perusahaan..."
                    aria-invalid={Boolean(errors.company_id)}
                />
            </Field>

            <Field id="name" label="Nama" error={errors.name} required>
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
                    onChange={(event) => setData('email', event.target.value)}
                    required
                    maxLength={255}
                    aria-invalid={Boolean(errors.email)}
                />
            </Field>

            <div className="grid gap-4 sm:grid-cols-2">
                <Field id="position" label="Jabatan" error={errors.position}>
                    <Input
                        id="position"
                        value={data.position}
                        onChange={(event) =>
                            setData('position', event.target.value)
                        }
                        maxLength={255}
                        placeholder="HRD, Supervisor Produksi, ..."
                        aria-invalid={Boolean(errors.position)}
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
        </>
    );
}
