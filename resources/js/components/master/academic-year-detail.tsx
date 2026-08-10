import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';

export type AcademicYearRecord = {
    id: string;
    name: string;
    start_date: string;
    end_date: string;
    is_active: boolean;
    school_classes_count: number;
    placements_count: number;
};

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'long' }).format(
        new Date(iso),
    );
}

export function AcademicYearDetail({ record }: { record: AcademicYearRecord }) {
    return (
        <dl className="grid gap-4 sm:grid-cols-2">
            <DetailItem label="Nama">{record.name}</DetailItem>
            <DetailItem label="Status">
                {record.is_active ? (
                    <Badge className="border-transparent bg-emerald-500/15 text-emerald-700 dark:text-emerald-400">
                        Aktif
                    </Badge>
                ) : (
                    <Badge variant="secondary">Tidak Aktif</Badge>
                )}
            </DetailItem>
            <DetailItem label="Tanggal Mulai">
                {formatDate(record.start_date)}
            </DetailItem>
            <DetailItem label="Tanggal Selesai">
                {formatDate(record.end_date)}
            </DetailItem>
            <DetailItem label="Jumlah Kelas">
                {record.school_classes_count}
            </DetailItem>
            <DetailItem label="Jumlah Penempatan">
                {record.placements_count}
            </DetailItem>
        </dl>
    );
}
