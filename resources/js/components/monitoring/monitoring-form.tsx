import { Form, Link } from '@inertiajs/react';
import { LoaderCircle, MapPin, Paperclip, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { Field } from '@/components/shared/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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

export function MonitoringForm({
    action,
    method,
    placements = [],
    placementLabel,
    values,
    submitLabel,
}: Props) {
    const geo = useGeolocation();
    const fileInput = useRef<HTMLInputElement>(null);
    const [files, setFiles] = useState<File[]>([]);
    const [placementId, setPlacementId] = useState(values?.placement_id ?? '');
    const [coords, setCoords] = useState<{
        latitude: number;
        longitude: number;
    } | null>(
        values?.latitude && values?.longitude
            ? {
                  latitude: Number(values.latitude),
                  longitude: Number(values.longitude),
              }
            : null,
    );

    const isCreate = method === 'post';
    const today = new Date().toISOString().slice(0, 10);

    return (
        // The file input carries a name, so Inertia's Form reads it from the DOM and
        // switches to FormData on its own.
        <Form action={action} method={method} className="max-w-3xl">
            {({ processing, errors }) => (
                <Card>
                    <CardContent className="grid gap-4">
                        {isCreate ? (
                            <Field
                                id="placement_id"
                                label="Penempatan"
                                error={errors.placement_id}
                                required
                            >
                                {/* Radix Select is not a native input; its value is
                                    mirrored into a hidden field for the post. */}
                                <input
                                    type="hidden"
                                    name="placement_id"
                                    value={placementId}
                                />
                                <Select
                                    value={placementId}
                                    onValueChange={setPlacementId}
                                >
                                    <SelectTrigger
                                        id="placement_id"
                                        aria-invalid={Boolean(
                                            errors.placement_id,
                                        )}
                                    >
                                        <SelectValue placeholder="Pilih siswa bimbingan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {placements.map((option) => (
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
                            label="Tanggal Kunjungan"
                            error={errors.visit_date}
                            required
                        >
                            <Input
                                id="visit_date"
                                name="visit_date"
                                type="date"
                                max={today}
                                defaultValue={values?.visit_date ?? today}
                                required
                                className="sm:max-w-56"
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
                                name="note"
                                rows={5}
                                defaultValue={values?.note}
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
                                name="recommendation"
                                rows={3}
                                defaultValue={values?.recommendation ?? ''}
                                maxLength={2000}
                                aria-invalid={Boolean(errors.recommendation)}
                            />
                        </Field>

                        <div className="grid gap-2 rounded-lg border p-3">
                            <div className="grid gap-0.5">
                                <p className="text-sm font-medium">
                                    Lokasi Kunjungan
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Opsional, sebagai bukti tambahan. Laporan
                                    tetap dapat disimpan tanpa lokasi.
                                </p>
                            </div>

                            <input
                                type="hidden"
                                name="latitude"
                                value={coords?.latitude ?? ''}
                            />
                            <input
                                type="hidden"
                                name="longitude"
                                value={coords?.longitude ?? ''}
                            />

                            <div className="flex flex-wrap items-center gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={geo.loading}
                                    onClick={async () => {
                                        const result = await geo.request();

                                        if (result) {
                                            setCoords({
                                                latitude: result.latitude,
                                                longitude: result.longitude,
                                            });
                                        }
                                    }}
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
                                    {coords
                                        ? 'Perbarui Lokasi'
                                        : 'Ambil Lokasi Saat Ini'}
                                </Button>

                                {coords ? (
                                    <>
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {coords.latitude.toFixed(5)},{' '}
                                            {coords.longitude.toFixed(5)}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setCoords(null)}
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

                        <Field
                            id="attachments"
                            label="Dokumentasi"
                            error={
                                errors.attachments ?? errors['attachments.0']
                            }
                            hint="Opsional. Maksimal 5 berkas, foto atau PDF, masing-masing maks 4 MB."
                        >
                            <Input
                                ref={fileInput}
                                id="attachments"
                                name="attachments[]"
                                type="file"
                                multiple
                                accept="image/*,application/pdf"
                                onChange={(event) =>
                                    setFiles(
                                        Array.from(event.target.files ?? []),
                                    )
                                }
                            />
                            {files.length > 0 ? (
                                <ul className="grid gap-1">
                                    {files.map((file) => (
                                        <li
                                            key={file.name}
                                            className="flex items-center gap-2 text-xs text-muted-foreground"
                                        >
                                            <Paperclip
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            {file.name}
                                        </li>
                                    ))}
                                    <li>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            // Clears the input too, not just the preview.
                                            onClick={() => {
                                                setFiles([]);

                                                if (fileInput.current) {
                                                    fileInput.current.value =
                                                        '';
                                                }
                                            }}
                                        >
                                            <X
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            Kosongkan pilihan
                                        </Button>
                                    </li>
                                </ul>
                            ) : null}
                        </Field>

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button variant="outline" asChild>
                                <Link href="/monitorings">Batal</Link>
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
