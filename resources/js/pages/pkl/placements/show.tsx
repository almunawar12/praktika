import { Link } from '@inertiajs/react';
import { Ban, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { PlacementActions } from '@/components/pkl/placement-actions';
import type { PlacementAbilities } from '@/components/pkl/placement-actions';
import { PlacementStatusBadge } from '@/components/pkl/placement-status-badge';
import type { PlacementStatus } from '@/components/pkl/placement-status-badge';
import { DeleteDialog } from '@/components/shared/delete-dialog';
import { DetailItem } from '@/components/shared/field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

type Props = {
    placement: {
        id: string;
        status: PlacementStatus;
        start_date: string;
        end_date: string;
        cancel_reason: string | null;
        student: {
            id: string;
            name: string;
            nis: string;
            nisn: string;
            school_class: {
                id: string;
                name: string;
                department: { code: string; name: string };
            };
        };
        company: {
            id: string;
            name: string;
            business_field: string;
            address: string;
            pic_name: string;
            pic_phone: string;
            quota: number;
            is_active: boolean;
        };
        teacher: {
            id: string;
            name: string;
            nip: string;
            user: { email: string };
        };
    };
    remainingQuota: number;
    can: PlacementAbilities;
};

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(
        new Date(iso),
    );
}

function durationInDays(start: string, end: string): number {
    const ms = new Date(end).getTime() - new Date(start).getTime();

    return Math.round(ms / 86_400_000) + 1;
}

export default function PlacementShow({
    placement,
    remainingQuota,
    can,
}: Props) {
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    return (
        <AppLayout
            title={`Penempatan — ${placement.student.name}`}
            description="Detail penempatan PKL"
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/pkl/placements">Kembali</Link>
                    </Button>

                    {can.update ? (
                        <Button variant="outline" asChild>
                            <Link href={`/pkl/placements/${placement.id}/edit`}>
                                <Pencil className="size-4" aria-hidden="true" />
                                Ubah
                            </Link>
                        </Button>
                    ) : null}

                    <PlacementActions placementId={placement.id} can={can} />

                    {can.delete ? (
                        <Button
                            variant="ghost"
                            className="text-destructive"
                            onClick={() => setConfirmingDelete(true)}
                        >
                            <Trash2 className="size-4" aria-hidden="true" />
                            Hapus Draft
                        </Button>
                    ) : null}
                </div>

                {placement.status === 'cancelled' && placement.cancel_reason ? (
                    <Alert variant="destructive">
                        <Ban className="size-4" aria-hidden="true" />
                        <AlertTitle>Penempatan dibatalkan</AlertTitle>
                        <AlertDescription>
                            {placement.cancel_reason}
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Penempatan</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4">
                                <DetailItem label="Status">
                                    <PlacementStatusBadge
                                        status={placement.status}
                                    />
                                </DetailItem>
                                <DetailItem label="Tanggal Mulai">
                                    {formatDate(placement.start_date)}
                                </DetailItem>
                                <DetailItem label="Tanggal Selesai">
                                    {formatDate(placement.end_date)}
                                </DetailItem>
                                <DetailItem label="Durasi">
                                    {durationInDays(
                                        placement.start_date,
                                        placement.end_date,
                                    )}{' '}
                                    hari
                                </DetailItem>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Siswa</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4">
                                <DetailItem label="Nama">
                                    <Link
                                        href={`/master/students/${placement.student.id}`}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {placement.student.name}
                                    </Link>
                                </DetailItem>
                                <DetailItem label="NIS / NISN">
                                    {placement.student.nis} /{' '}
                                    {placement.student.nisn}
                                </DetailItem>
                                <DetailItem label="Kelas">
                                    <Badge variant="outline">
                                        {placement.student.school_class.name} (
                                        {
                                            placement.student.school_class
                                                .department.code
                                        }
                                        )
                                    </Badge>
                                </DetailItem>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Guru Pembimbing</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4">
                                <DetailItem label="Nama">
                                    <Link
                                        href={`/master/teachers/${placement.teacher.id}`}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {placement.teacher.name}
                                    </Link>
                                </DetailItem>
                                <DetailItem label="NIP">
                                    {placement.teacher.nip}
                                </DetailItem>
                                <DetailItem label="Email">
                                    {placement.teacher.user.email}
                                </DetailItem>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-3">
                        <CardHeader>
                            <CardTitle>Perusahaan</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <DetailItem label="Nama">
                                    <Link
                                        href={`/master/companies/${placement.company.id}`}
                                        className="underline-offset-4 hover:underline"
                                    >
                                        {placement.company.name}
                                    </Link>
                                </DetailItem>
                                <DetailItem label="Bidang Usaha">
                                    <Badge variant="outline">
                                        {placement.company.business_field}
                                    </Badge>
                                </DetailItem>
                                <DetailItem label="Sisa Kuota">
                                    <span
                                        className={
                                            remainingQuota === 0
                                                ? 'text-destructive'
                                                : undefined
                                        }
                                    >
                                        {remainingQuota} dari{' '}
                                        {placement.company.quota}
                                    </span>
                                </DetailItem>
                                <DetailItem label="PIC">
                                    {placement.company.pic_name} ·{' '}
                                    {placement.company.pic_phone}
                                </DetailItem>
                                <div className="sm:col-span-2 lg:col-span-4">
                                    <DetailItem label="Alamat">
                                        <span className="whitespace-pre-line">
                                            {placement.company.address}
                                        </span>
                                    </DetailItem>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <DeleteDialog
                open={confirmingDelete}
                onOpenChange={setConfirmingDelete}
                action={`/pkl/placements/${placement.id}`}
                title="Hapus draft penempatan?"
                description="Hanya draft yang dapat dihapus. Penempatan yang sudah berjalan harus dibatalkan agar riwayatnya tetap tersimpan."
            />
        </AppLayout>
    );
}
