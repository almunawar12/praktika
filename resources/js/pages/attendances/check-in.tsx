import { router } from '@inertiajs/react';
import {
    CircleAlert,
    Clock,
    LoaderCircle,
    LogIn,
    LogOut,
    MapPin,
} from 'lucide-react';
import { useState } from 'react';
import {
    AttendanceStatusBadge,
    VerificationBadge,
} from '@/components/attendance/attendance-badges';
import type {
    AttendanceStatus,
    VerificationStatus,
} from '@/components/attendance/attendance-badges';
import { PhotoCapture } from '@/components/attendance/photo-capture';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useGeolocation } from '@/hooks/use-geolocation';
import AppLayout from '@/layouts/app-layout';

type Props = {
    placement: {
        id: string;
        company: string;
        start_date: string;
        end_date: string;
        geofence: {
            latitude: number;
            longitude: number;
            radius_m: number;
        } | null;
    } | null;
    today: {
        status: AttendanceStatus;
        status_label: string;
        check_in_at: string | null;
        check_out_at: string | null;
        verification_status: VerificationStatus;
        verification_label: string;
    } | null;
    withinPeriod: boolean;
};

function formatTime(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', { timeStyle: 'short' }).format(
        new Date(iso),
    );
}

export default function CheckIn({ placement, today, withinPeriod }: Props) {
    const geo = useGeolocation();
    const [photo, setPhoto] = useState<File | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [absenceStatus, setAbsenceStatus] = useState('permit');

    const submit = async (url: string) => {
        setErrors({});

        // Location is requested at submit time, so the coordinates sent are the ones
        // from the moment the student acted — not from whenever the page loaded.
        const coords = await geo.request();

        if (coords === null) {
            return;
        }

        if (photo === null) {
            setErrors({ photo: 'Foto wajib diambil.' });

            return;
        }

        const data = new FormData();
        data.append('latitude', String(coords.latitude));
        data.append('longitude', String(coords.longitude));
        data.append('photo', photo);

        setProcessing(true);
        router.post(url, data, {
            forceFormData: true,
            preserveScroll: true,
            onError: setErrors,
            onFinish: () => setProcessing(false),
        });
    };

    if (placement === null) {
        return (
            <AppLayout
                title="Absensi Hari Ini"
                description="Check in dan check out PKL"
            >
                <Alert>
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>Belum ada PKL aktif</AlertTitle>
                    <AlertDescription>
                        Absensi tersedia setelah Admin PKL mengaktifkan
                        penempatan Anda.
                    </AlertDescription>
                </Alert>
            </AppLayout>
        );
    }

    const checkedIn = today?.check_in_at != null;
    const checkedOut = today?.check_out_at != null;
    const isAbsenceDay = today != null && today.status !== 'present';

    return (
        <AppLayout title="Absensi Hari Ini" description={placement.company}>
            <div className="mx-auto flex w-full max-w-lg flex-col gap-4">
                {!withinPeriod ? (
                    <Alert variant="destructive">
                        <CircleAlert className="size-4" aria-hidden="true" />
                        <AlertTitle>Di luar periode PKL</AlertTitle>
                        <AlertDescription>
                            Periode PKL Anda {placement.start_date} sampai{' '}
                            {placement.end_date}.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {today ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Absensi Hari Ini</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-3">
                            <div className="flex flex-wrap items-center gap-2">
                                <AttendanceStatusBadge status={today.status} />
                                <VerificationBadge
                                    status={today.verification_status}
                                />
                            </div>
                            {today.status === 'present' ? (
                                <div className="flex items-center gap-4 text-sm">
                                    <span className="flex items-center gap-1.5">
                                        <LogIn
                                            className="size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        Masuk {formatTime(today.check_in_at)}
                                    </span>
                                    <span className="flex items-center gap-1.5">
                                        <LogOut
                                            className="size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        />
                                        Pulang {formatTime(today.check_out_at)}
                                    </span>
                                </div>
                            ) : null}
                        </CardContent>
                    </Card>
                ) : null}

                {geo.error ? (
                    <Alert variant="destructive">
                        <MapPin className="size-4" aria-hidden="true" />
                        <AlertDescription>{geo.error}</AlertDescription>
                    </Alert>
                ) : null}

                {placement.geofence ? (
                    <p className="text-xs text-muted-foreground">
                        Absensi hanya dapat dilakukan dalam radius{' '}
                        {placement.geofence.radius_m} meter dari lokasi{' '}
                        {placement.company}.
                    </p>
                ) : null}

                {withinPeriod && !checkedOut && !isAbsenceDay ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                {checkedIn ? 'Check Out' : 'Check In'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            <PhotoCapture
                                onCapture={setPhoto}
                                error={errors.photo}
                            />

                            {errors.latitude ? (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {errors.latitude}
                                </p>
                            ) : null}

                            <Button
                                size="lg"
                                disabled={processing || geo.loading}
                                onClick={() =>
                                    submit(
                                        checkedIn
                                            ? '/attendances/check-out'
                                            : '/attendances/check-in',
                                    )
                                }
                            >
                                {processing || geo.loading ? (
                                    <LoaderCircle
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                ) : checkedIn ? (
                                    <LogOut
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                ) : (
                                    <LogIn
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                )}
                                {geo.loading
                                    ? 'Mendeteksi lokasi...'
                                    : checkedIn
                                      ? 'Check Out Sekarang'
                                      : 'Check In Sekarang'}
                            </Button>
                        </CardContent>
                    </Card>
                ) : null}

                {withinPeriod && today === null ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Tidak dapat hadir?</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {/* Plain form post: no photo or location involved. */}
                            <form
                                className="grid gap-3"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    const form = new FormData(
                                        event.currentTarget,
                                    );
                                    router.post(
                                        '/attendances/absence',
                                        {
                                            status: absenceStatus,
                                            notes: String(
                                                form.get('notes') ?? '',
                                            ),
                                        },
                                        {
                                            preserveScroll: true,
                                            onError: setErrors,
                                        },
                                    );
                                }}
                            >
                                <div className="grid gap-2">
                                    <Label htmlFor="absence-status">
                                        Status
                                    </Label>
                                    <Select
                                        value={absenceStatus}
                                        onValueChange={setAbsenceStatus}
                                    >
                                        <SelectTrigger id="absence-status">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="permit">
                                                Izin
                                            </SelectItem>
                                            <SelectItem value="sick">
                                                Sakit
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="notes">Keterangan</Label>
                                    <Textarea
                                        id="notes"
                                        name="notes"
                                        rows={3}
                                        required
                                        minLength={5}
                                        maxLength={1000}
                                        placeholder="Jelaskan alasan Anda agar pembimbing dapat memverifikasi."
                                        aria-invalid={Boolean(errors.notes)}
                                    />
                                    {errors.notes ? (
                                        <p
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {errors.notes}
                                        </p>
                                    ) : null}
                                </div>

                                <Button type="submit" variant="outline">
                                    <Clock
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    Ajukan Izin / Sakit
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </AppLayout>
    );
}
