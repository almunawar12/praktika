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
import { Switch } from '@/components/ui/switch';
import type { SelectOption } from '@/types/table';

export type UserValues = {
    name: string;
    email: string;
    phone: string;
    is_active: boolean;
    role: string;
    password: string;
    password_confirmation: string;
};

type Props = FieldsProps<UserValues> & {
    roles: SelectOption[];
    showRole: boolean;
    showPassword: boolean;
};

export function UserFields({
    data,
    setData,
    errors,
    roles,
    showRole,
    showPassword,
}: Props) {
    return (
        <>
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

            <Field id="email" label="Email" error={errors.email} required>
                <Input
                    id="email"
                    type="email"
                    value={data.email}
                    onChange={(event) => setData('email', event.target.value)}
                    required
                    aria-invalid={Boolean(errors.email)}
                />
            </Field>

            <Field id="phone" label="No HP" error={errors.phone}>
                <Input
                    id="phone"
                    value={data.phone}
                    onChange={(event) => setData('phone', event.target.value)}
                    maxLength={30}
                    aria-invalid={Boolean(errors.phone)}
                />
            </Field>

            {showRole ? (
                <Field id="role" label="Role" error={errors.role} required>
                    <Select
                        value={data.role}
                        onValueChange={(value) => setData('role', value)}
                    >
                        <SelectTrigger
                            id="role"
                            aria-invalid={Boolean(errors.role)}
                        >
                            <SelectValue placeholder="Pilih role" />
                        </SelectTrigger>
                        <SelectContent>
                            {roles.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
            ) : null}

            {showPassword ? (
                <>
                    <Field
                        id="password"
                        label="Kata Sandi"
                        error={errors.password}
                        required
                    >
                        <Input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(event) =>
                                setData('password', event.target.value)
                            }
                            required
                            aria-invalid={Boolean(errors.password)}
                        />
                    </Field>
                    <Field
                        id="password_confirmation"
                        label="Konfirmasi Kata Sandi"
                        required
                    >
                        <Input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(event) =>
                                setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            required
                        />
                    </Field>
                </>
            ) : null}

            <Field id="is_active" label="Status Akun">
                <div className="flex items-center gap-3">
                    <Switch
                        id="is_active"
                        checked={data.is_active}
                        onCheckedChange={(checked) =>
                            setData('is_active', checked)
                        }
                    />
                    <span className="text-sm text-muted-foreground">
                        {data.is_active ? 'Aktif' : 'Nonaktif'}
                    </span>
                </div>
            </Field>
        </>
    );
}
