import { LoaderCircle, MapPin } from 'lucide-react';
import { Field } from '@/components/shared/field';
import type { FieldsProps } from '@/components/shared/form-modal';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useGeolocation } from '@/hooks/use-geolocation';

export type CompanyValues = {
    name: string;
    business_field: string;
    address: string;
    pic_name: string;
    pic_phone: string;
    quota: number;
    is_active: boolean;
    latitude: string;
    longitude: string;
    geofence_radius_m: string;
};

export function CompanyFields({
    data,
    setData,
    errors,
}: FieldsProps<CompanyValues>) {
    const geo = useGeolocation();

    const useCurrentLocation = async () => {
        const coords = await geo.request();

        if (coords !== null) {
            setData('latitude', coords.latitude.toFixed(6));
            setData('longitude', coords.longitude.toFixed(6));
        }
    };

    return (
        <>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    id="name"
                    label="Nama Perusahaan"
                    error={errors.name}
                    required
                >
                    <Input
                        id="name"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                        autoFocus
                        required
                        maxLength={255}
                        aria-invalid={Boolean(errors.name)}
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
                        value={data.business_field}
                        onChange={(event) =>
                            setData('business_field', event.target.value)
                        }
                        required
                        maxLength={255}
                        aria-invalid={Boolean(errors.business_field)}
                    />
                </Field>
            </div>

            <Field id="address" label="Alamat" error={errors.address} required>
                <Textarea
                    id="address"
                    value={data.address}
                    onChange={(event) => setData('address', event.target.value)}
                    required
                    rows={3}
                    maxLength={1000}
                    aria-invalid={Boolean(errors.address)}
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
                        value={data.pic_name}
                        onChange={(event) =>
                            setData('pic_name', event.target.value)
                        }
                        required
                        maxLength={255}
                        aria-invalid={Boolean(errors.pic_name)}
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
                        type="tel"
                        inputMode="tel"
                        value={data.pic_phone}
                        onChange={(event) =>
                            setData('pic_phone', event.target.value)
                        }
                        required
                        maxLength={20}
                        aria-invalid={Boolean(errors.pic_phone)}
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
                    type="number"
                    min={0}
                    max={65535}
                    value={data.quota}
                    onChange={(event) =>
                        setData(
                            'quota',
                            event.target.value === ''
                                ? 0
                                : Number(event.target.value),
                        )
                    }
                    required
                    className="sm:max-w-40"
                    aria-invalid={Boolean(errors.quota)}
                />
            </Field>

            <div className="grid gap-4 rounded-lg border p-3">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div className="grid gap-0.5">
                        <p className="text-sm font-medium">Lokasi Absensi</p>
                        <p className="text-xs text-muted-foreground">
                            Opsional. Ambil lokasi saat ini atau isi manual.
                            Jika diisi, siswa hanya dapat absen dalam radius
                            ini.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={useCurrentLocation}
                        disabled={geo.loading}
                    >
                        {geo.loading ? (
                            <LoaderCircle
                                className="size-4 animate-spin"
                                aria-hidden="true"
                            />
                        ) : (
                            <MapPin className="size-4" aria-hidden="true" />
                        )}
                        Ambil Lokasi Sekarang
                    </Button>
                </div>

                {geo.error ? (
                    <p role="alert" className="text-xs text-destructive">
                        {geo.error}
                    </p>
                ) : null}

                <div className="grid gap-4 sm:grid-cols-3">
                    <Field
                        id="latitude"
                        label="Lintang"
                        error={errors.latitude}
                    >
                        <Input
                            id="latitude"
                            type="number"
                            step="any"
                            min={-90}
                            max={90}
                            placeholder="-6.914744"
                            value={data.latitude}
                            onChange={(event) =>
                                setData('latitude', event.target.value)
                            }
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
                            type="number"
                            step="any"
                            min={-180}
                            max={180}
                            placeholder="107.609810"
                            value={data.longitude}
                            onChange={(event) =>
                                setData('longitude', event.target.value)
                            }
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
                            type="number"
                            min={10}
                            max={5000}
                            placeholder="200"
                            value={data.geofence_radius_m}
                            onChange={(event) =>
                                setData('geofence_radius_m', event.target.value)
                            }
                            aria-invalid={Boolean(errors.geofence_radius_m)}
                        />
                    </Field>
                </div>
            </div>

            <div className="flex items-center gap-3 rounded-lg border p-3">
                <Switch
                    id="is_active"
                    checked={data.is_active}
                    onCheckedChange={(checked) => setData('is_active', checked)}
                />
                <div className="grid gap-0.5">
                    <Label htmlFor="is_active">Perusahaan aktif</Label>
                    <p className="text-xs text-muted-foreground">
                        Perusahaan nonaktif tidak dapat dipilih saat penempatan
                        PKL.
                    </p>
                </div>
            </div>
        </>
    );
}
