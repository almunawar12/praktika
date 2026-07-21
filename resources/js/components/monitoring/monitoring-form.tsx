import { useForm } from '@inertiajs/react';
import { LoaderCircle, MapPin, X } from 'lucide-react';
import { PhotoCapture } from '@/components/attendance/photo-capture';
import { Combobox } from '@/components/shared/combobox';
import { Field } from '@/components/shared/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useGeolocation } from '@/hooks/use-geolocation';
import type { SelectOption } from '@/types/table';

export type MonitoringValues = {
    id?: string;
    placement_id: string;
    visit_date: string;
    note: string;
    recommendation: string | null;
    latitude: string | null;
    longitude: string | null;
};

type Props = {
    action: string;
    method: 'post' | 'put';
    /** Only on create — the placement is fixed once the report exists. */
    placements?: SelectOption[];
    /** Shown instead of the picker when editing. */
    placementLabel?: string;
    values?: MonitoringValues;
    submitLabel: string;
};

type FormData = {
    placement_id: string;
    visit_date: string;
    note: string;
    recommendation: string;
    latitude: string;
    longitude: string;
    attachments: File[];
};

/** A Date rendered as the `YYYY-MM-DDTHH:mm` a datetime-local input expects. */
function toLocalInput(date: Date): string {
    const offset = date.getTimezoneOffset() * 60_000;

    return new Date(date.getTime() - offset).toISOString().slice(0, 16);
}

export function MonitoringForm({
    action,
    method,
    placements = [],
    placementLabel,
    values,
    submitLabel,
}: Props) {
    const geo = useGeolocation();
    const now = toLocalInput(new Date());

    const form = useForm<FormData>({
        placement_id: values?.placement_id ?? '',
        visit_date: values?.visit_date
            ? toLocalInput(new Date(values.visit_date))
            : now,
        note: values?.note ?? '',
        recommendation: values?.recommendation ?? '',
        latitude: values?.latitude ?? '',
        longitude: values?.longitude ?? '',
        attachments: [],
    });

    const { data, setData, errors, processing } = form;
    const isCreate = method === 'post';

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = { preserveScroll: true };

        if (method === 'post') {
            form.post(action, options);
        } else {
            form.put(action, options);
        }
    };

    const captureLocation = async () => {
        const result = await geo.request();

        if (result) {
            setData('latitude', result.latitude.toFixed(6));
            setData('longitude', result.longitude.toFixed(6));
        }
    };

    const hasCoords = data.latitude !== '' && data.longitude !== '';

    return (
        <form onSubmit={submit} className="max-w-3xl">
            <Card>
                <CardContent className="grid gap-4">
                    {isCreate ? (
                        <Field
                            id="placement_id"
                            label="Penempatan"
                            error={errors.placement_id}
                            required
                        >
                            <Combobox
                                id="placement_id"
                                value={data.placement_id}
                                onValueChange={(value) =>
                                    setData('placement_id', value)
                                }
                                options={placements}
                                placeholder="Pilih siswa bimbingan"
                                searchPlaceholder="Cari siswa..."
                                aria-invalid={Boolean(errors.placement_id)}
                            />
                        </Field>
                    ) : (
                        <div className="grid gap-1">
                            <span className="text-sm text-muted-foreground">
                                Penempatan
                            </span>
                            <span className="text-sm font-medium">
                                {placementLabel}
                            </span>
                        </div>
                    )}

                    <Field
                        id="visit_date"
                        label="Waktu Kunjungan"
                        error={errors.visit_date}
                        required
                    >
                        <Input
                            id="visit_date"
                            type="datetime-local"
                            max={now}
                            value={data.visit_date}
                            onChange={(event) =>
                                setData('visit_date', event.target.value)
                            }
                            required
                            className="sm:max-w-64"
                            aria-invalid={Boolean(errors.visit_date)}
                        />
                    </Field>

                    <Field
                        id="note"
                        label="Catatan Kunjungan"
                        error={errors.note}
                        required
                        hint="Kondisi siswa di lapangan, hasil diskusi dengan pembimbing industri."
                    >
                        <Textarea
                            id="note"
                            rows={5}
                            value={data.note}
                            onChange={(event) =>
                                setData('note', event.target.value)
                            }
                            required
                            minLength={10}
                            maxLength={2000}
                            aria-invalid={Boolean(errors.note)}
                        />
                    </Field>

                    <Field
                        id="recommendation"
                        label="Rekomendasi"
                        error={errors.recommendation}
                        hint="Opsional. Tindak lanjut untuk siswa atau perusahaan."
                    >
                        <Textarea
                            id="recommendation"
                            rows={3}
                            value={data.recommendation}
                            onChange={(event) =>
                                setData('recommendation', event.target.value)
                            }
                            maxLength={2000}
                            aria-invalid={Boolean(errors.recommendation)}
                        />
                    </Field>

                    <Field
                        id="attachments"
                        label="Foto Kunjungan"
                        error={errors.attachments ?? errors['attachments.0']}
                        hint="Diambil langsung dari kamera sebagai bukti kunjungan."
                    >
                        <PhotoCapture
                            onCapture={(file) =>
                                setData('attachments', file ? [file] : [])
                            }
                            error={
                                errors.attachments ?? errors['attachments.0']
                            }
                        />
                    </Field>

                    <div className="grid gap-2 rounded-lg border p-3">
                        <div className="grid gap-0.5">
                            <p className="text-sm font-medium">
                                Lokasi Kunjungan
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Opsional, sebagai bukti tambahan. Laporan tetap
                                dapat disimpan tanpa lokasi.
                            </p>
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={geo.loading}
                                onClick={captureLocation}
                            >
                                {geo.loading ? (
                                    <LoaderCircle
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <MapPin
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                {hasCoords
                                    ? 'Perbarui Lokasi'
                                    : 'Ambil Lokasi Saat Ini'}
                            </Button>

                            {hasCoords ? (
                                <>
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {data.latitude}, {data.longitude}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setData('latitude', '');
                                            setData('longitude', '');
                                        }}
                                    >
                                        <X
                                            className="size-3"
                                            aria-hidden="true"
                                        />
                                        Hapus
                                    </Button>
                                </>
                            ) : null}
                        </div>

                        {geo.error ? (
                            <p className="text-xs text-muted-foreground">
                                {geo.error}
                            </p>
                        ) : null}
                    </div>

                    <div className="flex justify-end">
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
        </form>
    );
}
