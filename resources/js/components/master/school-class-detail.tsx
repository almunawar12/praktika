import { Link } from '@inertiajs/react';
import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';

export type SchoolClassRecord = {
    id: string;
    name: string;
    is_active: boolean;
    students_count: number;
    department: { id: string; code: string; name: string };
};

export function SchoolClassDetail({ record }: { record: SchoolClassRecord }) {
    return (
        <dl className="grid gap-4 sm:grid-cols-2">
            <DetailItem label="Nama Kelas">{record.name}</DetailItem>
            <DetailItem label="Jurusan">
                <Badge variant="outline">
                    {record.department.code} — {record.department.name}
                </Badge>
            </DetailItem>
            <DetailItem label="Status">
                {record.is_active ? (
                    <Badge variant="secondary">Aktif</Badge>
                ) : (
                    <Badge variant="outline">Nonaktif</Badge>
                )}
            </DetailItem>
            <DetailItem label="Jumlah Siswa">
                <Link
                    href={`/master/students?class_id=${record.id}`}
                    className="underline-offset-4 hover:underline"
                >
                    {record.students_count} siswa
                </Link>
            </DetailItem>
        </dl>
    );
}
