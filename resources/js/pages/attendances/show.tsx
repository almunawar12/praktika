import { Form, Link } from '@inertiajs/react';
import { CircleAlert, MapPin } from 'lucide-react';
import { useState } from 'react';
import {
    AttendanceStatusBadge,
    VerificationBadge,
} from '@/components/attendance/attendance-badges';
import type {
    AttendanceStatus,
    VerificationStatus,
} from '@/components/attendance/attendance-badges';
import { DetailItem } from '@/components/shared/field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

type Props = {
    attendance: {
        id: string;
        date: string;
        status: AttendanceStatus;
        verification_status: VerificationStatus;
        check_in_at: string | null;
        check_out_at: string | null;
        check_in_distance_m: number | null;
        check_out_distance_m: number | null;
        check_in_photo_url: string | null;
        check_out_photo_url: string | null;
        check_in_outside_geofence: boolean | null;
        notes: string | null;
        verification_note: string | null;
        placement: {
            id: string;
            student: { id: string; name: string; nis: string };
            company: {
                id: string;
                name: string;
                geofence_radius_m: number | null;
            };
        };
        verifier: { id: string; name: string } | null;
    };
    can: { verify: boolean };
};

function formatDateTime(iso: string | null): string {
    if (iso === null) {
        return '—';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

function formatDistance(metres: number | null): string {
    if (metres === null) {
        return 'Tidak diketahui';
    }

    return metres >= 1000
        ? `${(metres / 1000).toFixed(1).replace('.', ',')} km dari lokasi`
        : `${metres} meter dari lokasi`;
}

export default function AttendanceShow({ attendance, can }: Props) {
    const [decision, setDecision] = useState<'verified' | 'rejected'>(
        'verified',
    );

    return (
        <AppLayout
            title={`Absensi — ${attendance.placement.student.name}`}
            description={formatDateTime(attendance.date)}
        >
            <div className="flex flex-col gap-4">
                <Button variant="outline" className="w-fit" asChild>
                    <Link href="/attendances">Kembali</Link>
                </Button>

                {attendance.check_in_outside_geofence ? (
                    <Alert variant="destructive">
                        <MapPin className="size-4" aria-hidden="true" />
                        <AlertTitle>Di luar radius lokasi</AlertTitle>
                        <AlertDescription>
                            Absensi tercatat{' '}
                            {formatDistance(attendance.check_in_distance_m)}, di
                            luar radius{' '}
                            {attendance.placement.company.geofence_radius_m}{' '}
                            meter.
                        </AlertDescription>
                    </Alert>
                ) : null}

                {attendance.verification_status === 'rejected' &&
                attendance.verification_note ? (
                    <Alert variant="destructive">
                        <CircleAlert className="size-4" aria-hidden="true" />
                        <AlertTitle>Absensi ditolak</AlertTitle>
                        <AlertDescription>
                            {attendance.verification_note}
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Ringkasan</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <DetailItem label="Status">
                                    <AttendanceStatusBadge
                                        status={attendance.status}
                                    />
                                </DetailItem>
                                <DetailItem label="Verifikasi">
                                    <VerificationBadge
                                        status={attendance.verification_status}
                                    />
                                </DetailItem>
                                <DetailItem label="Siswa">
                                    {attendance.placement.student.name}
                                </DetailItem>
                                <DetailItem label="Perusahaan">
                                    {attendance.placement.company.name}
                                </DetailItem>
                                <DetailItem label="Check In">
                                    {formatDateTime(attendance.check_in_at)}
                                </DetailItem>
                                <DetailItem label="Check Out">
                                    {formatDateTime(attendance.check_out_at)}
                                </DetailItem>
                                {attendance.notes ? (
                                    <div className="sm:col-span-2">
                                        <DetailItem label="Keterangan Siswa">
                                            {attendance.notes}
                                        </DetailItem>
                                    </div>
                                ) : null}
                                {attendance.verifier ? (
                                    <DetailItem label="Diverifikasi oleh">
                                        {attendance.verifier.name}
                                    </DetailItem>
                                ) : null}
                            </dl>
                        </CardContent>
                    </Card>

                    {attendance.check_in_photo_url ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Bukti Foto</CardTitle>
                            </CardHeader>
                            <CardContent className="grid gap-4 sm:grid-cols-2">
                                <div className="grid gap-1.5">
                                    <p className="text-xs text-muted-foreground">
                                        Check In ·{' '}
                                        {formatDistance(
                                            attendance.check_in_distance_m,
                                        )}
                                    </p>
                                    <img
                                        src={attendance.check_in_photo_url}
                                        alt="Foto check in"
                                        className="aspect-[4/3] w-full rounded-lg border object-cover"
                                    />
                                </div>
                                {attendance.check_out_photo_url ? (
                                    <div className="grid gap-1.5">
                                        <p className="text-xs text-muted-foreground">
                                            Check Out ·{' '}
                                            {formatDistance(
                                                attendance.check_out_distance_m,
                                            )}
                                        </p>
                                        <img
                                            src={attendance.check_out_photo_url}
                                            alt="Foto check out"
                                            className="aspect-[4/3] w-full rounded-lg border object-cover"
                                        />
                                    </div>
                                ) : null}
                            </CardContent>
                        </Card>
                    ) : null}
                </div>

                {can.verify ? (
                    <Card className="max-w-2xl">
                        <CardHeader>
                            <CardTitle>Verifikasi Absensi</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Form
                                action={`/attendances/${attendance.id}/verify`}
                                method="put"
                                className="grid gap-4"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="decision"
                                            value={decision}
                                        />

                                        <div className="grid gap-2">
                                            <Label htmlFor="note">
                                                Catatan
                                                {decision === 'rejected' ? (
                                                    <span
                                                        className="text-destructive"
                                                        aria-hidden="true"
                                                    >
                                                        *
                                                    </span>
                                                ) : null}
                                            </Label>
                                            <Textarea
                                                id="note"
                                                name="note"
                                                rows={2}
                                                maxLength={1000}
                                                placeholder={
                                                    decision === 'rejected'
                                                        ? 'Wajib: jelaskan alasan penolakan agar siswa memahaminya.'
                                                        : 'Opsional'
                                                }
                                                aria-invalid={Boolean(
                                                    errors.note,
                                                )}
                                            />
                                            {errors.note ? (
                                                <p
                                                    role="alert"
                                                    className="text-sm text-destructive"
                                                >
                                                    {errors.note}
                                                </p>
                                            ) : null}
                                        </div>

                                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                className="text-destructive"
                                                disabled={processing}
                                                onClick={() =>
                                                    setDecision('rejected')
                                                }
                                            >
                                                Tolak
                                            </Button>
                                            <Button
                                                type="submit"
                                                disabled={processing}
                                                onClick={() =>
                                                    setDecision('verified')
                                                }
                                            >
                                                Verifikasi
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </AppLayout>
    );
}
