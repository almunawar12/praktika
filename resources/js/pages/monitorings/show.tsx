import { Link } from '@inertiajs/react';
import { FileText, MapPin, Paperclip, Pencil } from 'lucide-react';
import { useState } from 'react';
import { DeleteDialog } from '@/components/shared/delete-dialog';
import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

type Attachment = {
    id: string;
    original_name: string;
    caption: string | null;
    is_image: boolean;
    url: string;
};

type Props = {
    monitoring: {
        id: string;
        visit_date: string;
        note: string;
        recommendation: string | null;
        latitude: string | null;
        longitude: string | null;
        has_location: boolean;
        attachments: Attachment[];
        teacher: { id: string; name: string; nip: string };
        placement: {
            id: string;
            student: { id: string; name: string; nis: string };
            company: { id: string; name: string; address: string };
        };
    };
    can: { update: boolean; delete: boolean };
};

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' }).format(
        new Date(iso),
    );
}

export default function MonitoringShow({ monitoring, can }: Props) {
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    return (
        <AppLayout
            title="Laporan Kunjungan"
            description={formatDate(monitoring.visit_date)}
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/monitorings">Kembali</Link>
                    </Button>

                    {can.update ? (
                        <Button asChild>
                            <Link href={`/monitorings/${monitoring.id}/edit`}>
                                <Pencil className="size-4" aria-hidden="true" />
                                Ubah
                            </Link>
                        </Button>
                    ) : null}

                    {can.delete ? (
                        <Button
                            variant="ghost"
                            className="text-destructive"
                            onClick={() => setConfirmingDelete(true)}
                        >
                            Hapus
                        </Button>
                    ) : null}
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Hasil Kunjungan</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4">
                                <DetailItem label="Catatan">
                                    <span className="whitespace-pre-line">
                                        {monitoring.note}
                                    </span>
                                </DetailItem>
                                <DetailItem label="Rekomendasi">
                                    <span className="whitespace-pre-line">
                                        {monitoring.recommendation ?? '—'}
                                    </span>
                                </DetailItem>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4">
                                <DetailItem label="Siswa">
                                    <Link
                                        href={`/pkl/placements/${monitoring.placement.id}`}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {monitoring.placement.student.name}
                                    </Link>
                                </DetailItem>
                                <DetailItem label="Perusahaan">
                                    {monitoring.placement.company.name}
                                </DetailItem>
                                <DetailItem label="Guru Pembimbing">
                                    {monitoring.teacher.name}
                                </DetailItem>
                                <DetailItem label="Lokasi Kunjungan">
                                    {monitoring.has_location ? (
                                        <a
                                            href={`https://www.openstreetmap.org/?mlat=${monitoring.latitude}&mlon=${monitoring.longitude}#map=17/${monitoring.latitude}/${monitoring.longitude}`}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <Badge
                                                variant="outline"
                                                className="gap-1"
                                            >
                                                <MapPin
                                                    className="size-3"
                                                    aria-hidden="true"
                                                />
                                                {Number(
                                                    monitoring.latitude,
                                                ).toFixed(5)}
                                                ,{' '}
                                                {Number(
                                                    monitoring.longitude,
                                                ).toFixed(5)}
                                            </Badge>
                                        </a>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Tidak direkam
                                        </span>
                                    )}
                                </DetailItem>
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                {monitoring.attachments.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Dokumentasi</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {monitoring.attachments.map((attachment) => (
                                <a
                                    key={attachment.id}
                                    href={attachment.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="grid gap-1.5 rounded-lg border p-2 hover:bg-muted/50"
                                >
                                    {attachment.is_image ? (
                                        <img
                                            src={attachment.url}
                                            alt={
                                                attachment.caption ??
                                                attachment.original_name
                                            }
                                            className="aspect-[4/3] w-full rounded object-cover"
                                        />
                                    ) : (
                                        <span className="flex aspect-[4/3] w-full items-center justify-center rounded bg-muted">
                                            <FileText
                                                className="size-8 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                        </span>
                                    )}
                                    <span className="flex items-center gap-1 truncate text-xs text-muted-foreground">
                                        <Paperclip
                                            className="size-3 shrink-0"
                                            aria-hidden="true"
                                        />
                                        {attachment.original_name}
                                    </span>
                                </a>
                            ))}
                        </CardContent>
                    </Card>
                ) : null}
            </div>

            <DeleteDialog
                open={confirmingDelete}
                onOpenChange={setConfirmingDelete}
                action={`/monitorings/${monitoring.id}`}
                title="Hapus laporan kunjungan?"
                description="Laporan beserta dokumentasinya akan dihapus."
            />
        </AppLayout>
    );
}
