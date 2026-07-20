import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';

export type DepartmentRecord = {
    id: string;
    code: string;
    name: string;
    is_active: boolean;
    school_classes_count: number;
    students_count: number;
    school_classes: { id: string; name: string }[];
};

export function DepartmentDetail({ record }: { record: DepartmentRecord }) {
    return (
        <div className="grid gap-4">
            <dl className="grid gap-4 sm:grid-cols-2">
                <DetailItem label="Kode">
                    <Badge variant="outline">{record.code}</Badge>
                </DetailItem>
                <DetailItem label="Nama">{record.name}</DetailItem>
                <DetailItem label="Status">
                    {record.is_active ? (
                        <Badge variant="secondary">Aktif</Badge>
                    ) : (
                        <Badge variant="outline">Nonaktif</Badge>
                    )}
                </DetailItem>
                <DetailItem label="Jumlah Kelas">
                    {record.school_classes_count}
                </DetailItem>
                <DetailItem label="Jumlah Siswa">
                    {record.students_count}
                </DetailItem>
            </dl>

            <div>
                <p className="mb-2 text-sm font-medium">Kelas</p>
                {record.school_classes.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Belum ada kelas pada jurusan ini.
                    </p>
                ) : (
                    <ul className="flex flex-wrap gap-2">
                        {record.school_classes.map((schoolClass) => (
                            <li key={schoolClass.id}>
                                <Badge variant="secondary">
                                    {schoolClass.name}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
