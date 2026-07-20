import { Info } from 'lucide-react';
import { Field } from '@/components/shared/field';
import type { FieldsProps } from '@/components/shared/form-modal';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Input } from '@/components/ui/input';

export type TeacherValues = {
    nip: string;
    name: string;
    email: string;
    phone: string;
};

type Props = FieldsProps<TeacherValues> & {
    mode: 'create' | 'edit';
};

export function TeacherFields({ data, setData, errors, mode }: Props) {
    return (
        <>
            {mode === 'create' ? (
                <Alert>
                    <Info className="size-4" aria-hidden="true" />
                    <AlertDescription>
                        Akun login guru dibuat otomatis dari email ini. Kata
                        sandi diacak dan tidak ditampilkan — minta guru
                        menggunakan menu "Lupa kata sandi" untuk mengaktifkan
                        akunnya.
                    </AlertDescription>
                </Alert>
            ) : null}

            <Field id="nip" label="NIP" error={errors.nip} required>
                <Input
                    id="nip"
                    value={data.nip}
                    onChange={(event) => setData('nip', event.target.value)}
                    autoFocus
                    required
                    maxLength={30}
                    inputMode="numeric"
                    aria-invalid={Boolean(errors.nip)}
                />
            </Field>

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

            <Field id="phone" label="No HP" error={errors.phone}>
                <Input
                    id="phone"
                    type="tel"
                    value={data.phone}
                    onChange={(event) => setData('phone', event.target.value)}
                    maxLength={20}
                    inputMode="tel"
                    aria-invalid={Boolean(errors.phone)}
                />
            </Field>
        </>
    );
}
