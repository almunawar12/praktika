import { Form, Link } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { Field } from '@/components/shared/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

export type CompanyValues = {
    id?: string;
    name: string;
    business_field: string;
    address: string;
    pic_name: string;
    pic_phone: string;
    quota: number;
    is_active: boolean;
    latitude: string | null;
    longitude: string | null;
    geofence_radius_m: number | null;
};

type Props = {
    action: string;
    method: 'post' | 'put';
    values?: CompanyValues;
    submitLabel: string;
};

export function CompanyForm({ action, method, values, submitLabel }: Props) {
    const [isActive, setIsActive] = useState(values?.is_active ?? true);

    return (
        <Form action={action} method={method} className="max-w-3xl">
            {({ processing, errors }) => (
                <Card>
                    <CardContent className="grid gap-4">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="name"
                                label="Nama Perusahaan"
                                error={errors.name}
                                required
                            >
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={values?.name}
                                    autoFocus
                                    required
                                    maxLength={255}
                                    aria-invalid={Boolean(errors.name)}
                                    aria-describedby={
                                        errors.name ? 'name-error' : undefined
                                    }
                                />
                            </Field>

                            <Field
                                id="business_field"
                                label="Bidang Usaha"
                                error={errors.business_field}
                                required
                            >
                                <Input
                                    id="business_field"
                                    name="business_field"
                                    defaultValue={values?.business_field}
                                    required
                                    maxLength={255}
                                    aria-invalid={Boolean(
                                        errors.business_field,
                                    )}
                                    aria-describedby={
                                        errors.business_field
                                            ? 'business_field-error'
                                            : undefined
                                    }
                                />
                            </Field>
                        </div>

                        <Field
                            id="address"
                            label="Alamat"
                            error={errors.address}
                            required
                        >
                            <Textarea
                                id="address"
                                name="address"
                                defaultValue={values?.address}
                                required
                                rows={3}
                                maxLength={1000}
                                aria-invalid={Boolean(errors.address)}
                                aria-describedby={
                                    errors.address ? 'address-error' : undefined
                                }
                            />
                        </Field>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="pic_name"
                                label="Nama PIC"
                                error={errors.pic_name}
                                required
                            >
                                <Input
                                    id="pic_name"
                                    name="pic_name"
                                    defaultValue={values?.pic_name}
                                    required
                                    maxLength={255}
                                    aria-invalid={Boolean(errors.pic_name)}
                                    aria-describedby={
                                        errors.pic_name
                                            ? 'pic_name-error'
                                            : undefined
                                    }
                                />
                            </Field>

                            <Field
                                id="pic_phone"
                                label="No HP PIC"
                                error={errors.pic_phone}
                                required
                            >
                                <Input
                                    id="pic_phone"
                                    name="pic_phone"
                                    type="tel"
                                    inputMode="tel"
                                    defaultValue={values?.pic_phone}
                                    required
                                    maxLength={20}
                                    aria-invalid={Boolean(errors.pic_phone)}
                                    aria-describedby={
                                        errors.pic_phone
                                            ? 'pic_phone-error'
                                            : undefined
                                    }
                                />
                            </Field>
                        </div>

                        <Field
                            id="quota"
                            label="Kuota"
                            error={errors.quota}
                            required
                            hint="Jumlah siswa PKL yang dapat diterima. Isi 0 jika sedang tidak menerima."
                        >
                            <Input
                                id="quota"
                                name="quota"
                                type="number"
                                min={0}
                                max={65535}
                                defaultValue={values?.quota ?? 0}
                                required
                                className="sm:max-w-40"
                                aria-invalid={Boolean(errors.quota)}
                                aria-describedby={
                                    errors.quota ? 'quota-error' : undefined
                                }
                            />
                        </Field>

                        <div className="grid gap-4 rounded-lg border p-3">
                            <div className="grid gap-0.5">
                                <p className="text-sm font-medium">
                                    Lokasi Absensi
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Opsional. Jika diisi, siswa hanya dapat
                                    absen dalam radius ini. Kosongkan jika
                                    perusahaan tidak menerapkan batas lokasi.
                                </p>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field
                                    id="latitude"
                                    label="Lintang"
                                    error={errors.latitude}
                                >
                                    <Input
                                        id="latitude"
                                        name="latitude"
                                        type="number"
                                        step="any"
                                        min={-90}
                                        max={90}
                                        placeholder="-6.914744"
                                        defaultValue={values?.latitude ?? ''}
                                        aria-invalid={Boolean(errors.latitude)}
                                    />
                                </Field>

                                <Field
                                    id="longitude"
                                    label="Bujur"
                                    error={errors.longitude}
                                >
                                    <Input
                                        id="longitude"
                                        name="longitude"
                                        type="number"
                                        step="any"
                                        min={-180}
                                        max={180}
                                        placeholder="107.609810"
                                        defaultValue={values?.longitude ?? ''}
                                        aria-invalid={Boolean(errors.longitude)}
                                    />
                                </Field>

                                <Field
                                    id="geofence_radius_m"
                                    label="Radius (meter)"
                                    error={errors.geofence_radius_m}
                                >
                                    <Input
                                        id="geofence_radius_m"
                                        name="geofence_radius_m"
                                        type="number"
                                        min={10}
                                        max={5000}
                                        placeholder="200"
                                        defaultValue={
                                            values?.geofence_radius_m ?? ''
                                        }
                                        aria-invalid={Boolean(
                                            errors.geofence_radius_m,
                                        )}
                                    />
                                </Field>
                            </div>
                        </div>

                        <div className="flex items-center gap-3 rounded-lg border p-3">
                            {/* Switch is not a native input, so the value is posted through
                                a hidden field. */}
                            <input
                                type="hidden"
                                name="is_active"
                                value={isActive ? '1' : '0'}
                            />
                            <Switch
                                id="is_active"
                                checked={isActive}
                                onCheckedChange={setIsActive}
                            />
                            <div className="grid gap-0.5">
                                <Label htmlFor="is_active">
                                    Perusahaan aktif
                                </Label>
                                <p className="text-xs text-muted-foreground">
                                    Perusahaan nonaktif tidak dapat dipilih saat
                                    penempatan PKL.
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button variant="outline" asChild>
                                <Link href="/master/companies">Batal</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? (
                                    <LoaderCircle
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                ) : null}
                                {submitLabel}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}
        </Form>
    );
}
