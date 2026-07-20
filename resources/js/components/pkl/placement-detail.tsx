import { Link } from '@inertiajs/react';
import { Ban } from 'lucide-react';
import {
    PlacementActions,
    type PlacementAbilities,
} from '@/components/pkl/placement-actions';
import {
    PlacementStatusBadge,
    type PlacementStatus,
} from '@/components/pkl/placement-status-badge';
import { DetailItem } from '@/components/shared/field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';

export type PlacementRecord = {
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

export type PlacementDetailData = {
    placement: PlacementRecord;
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

export function PlacementDetail({
    placement,
    remainingQuota,
    can,
}: PlacementDetailData) {
    return (
        <div className="grid gap-4">
            {(can.activate || can.complete || can.cancel) && (
                <div className="flex flex-wrap items-center gap-2">
                    <PlacementActions placementId={placement.id} can={can} />
                </div>
            )}

            {placement.status === 'cancelled' && placement.cancel_reason ? (
                <Alert variant="destructive">
                    <Ban className="size-4" aria-hidden="true" />
                    <AlertTitle>Penempatan dibatalkan</AlertTitle>
                    <AlertDescription>
                        {placement.cancel_reason}
                    </AlertDescription>
                </Alert>
            ) : null}

            <section>
                <p className="mb-2 text-sm font-medium">Penempatan</p>
                <dl className="grid gap-4 sm:grid-cols-2">
                    <DetailItem label="Status">
                        <PlacementStatusBadge status={placement.status} />
                    </DetailItem>
                    <DetailItem label="Durasi">
                        {durationInDays(
                            placement.start_date,
                            placement.end_date,
                        )}{' '}
                        hari
                    </DetailItem>
                    <DetailItem label="Tanggal Mulai">
                        {formatDate(placement.start_date)}
                    </DetailItem>
                    <DetailItem label="Tanggal Selesai">
                        {formatDate(placement.end_date)}
                    </DetailItem>
                </dl>
            </section>

            <section>
                <p className="mb-2 text-sm font-medium">Siswa</p>
                <dl className="grid gap-4 sm:grid-cols-2">
                    <DetailItem label="Nama">
                        <Link
                            href={`/master/students/${placement.student.id}`}
                            className="underline-offset-4 hover:underline"
                        >
                            {placement.student.name}
                        </Link>
                    </DetailItem>
                    <DetailItem label="NIS / NISN">
                        {placement.student.nis} / {placement.student.nisn}
                    </DetailItem>
                    <DetailItem label="Kelas">
                        <Badge variant="outline">
                            {placement.student.school_class.name} (
                            {placement.student.school_class.department.code})
                        </Badge>
                    </DetailItem>
                </dl>
            </section>

            <section>
                <p className="mb-2 text-sm font-medium">Guru Pembimbing</p>
                <dl className="grid gap-4 sm:grid-cols-2">
                    <DetailItem label="Nama">
                        <Link
                            href={`/master/teachers/${placement.teacher.id}`}
                            className="underline-offset-4 hover:underline"
                        >
                            {placement.teacher.name}
                        </Link>
                    </DetailItem>
                    <DetailItem label="NIP">{placement.teacher.nip}</DetailItem>
                    <DetailItem label="Email">
                        {placement.teacher.user.email}
                    </DetailItem>
                </dl>
            </section>

            <section>
                <p className="mb-2 text-sm font-medium">Perusahaan</p>
                <dl className="grid gap-4 sm:grid-cols-2">
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
                            {remainingQuota} dari {placement.company.quota}
                        </span>
                    </DetailItem>
                    <DetailItem label="PIC">
                        {placement.company.pic_name} ·{' '}
                        {placement.company.pic_phone}
                    </DetailItem>
                    <div className="sm:col-span-2">
                        <DetailItem label="Alamat">
                            <span className="whitespace-pre-line">
                                {placement.company.address}
                            </span>
                        </DetailItem>
                    </div>
                </dl>
            </section>
        </div>
    );
}
