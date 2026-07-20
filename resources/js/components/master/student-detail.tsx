import { Link } from '@inertiajs/react';
import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';

export type StudentRecord = {
    id: string;
    nis: string;
    nisn: string;
    name: string;
    phone: string | null;
    user: {
        id: string;
        email: string;
        is_active: boolean;
        last_login_at: string | null;
    };
    school_class: {
        id: string;
        name: string;
        department: { id: string; code: string; name: string };
    };
};

function formatDate(iso: string | null): string {
    if (iso === null) {
        return 'Belum pernah masuk';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export function StudentDetail({ record }: { record: StudentRecord }) {
    return (
        <div className="grid gap-6">
            <section>
                <p className="mb-2 text-sm font-medium">Data Siswa</p>
                <dl className="grid gap-4 sm:grid-cols-2">
                    <DetailItem label="NIS">{record.nis}</DetailItem>
                    <DetailItem label="NISN">{record.nisn}</DetailItem>
                    <DetailItem label="Nama">{record.name}</DetailItem>
                    <DetailItem label="No HP">{record.phone ?? '—'}</DetailItem>
                    <DetailItem label="Kelas">
                        <Link href={`/master/classes/${record.school_class.id}`}>
                            <Badge variant="outline">
                                {record.school_class.name}
                            </Badge>
                        </Link>
                    </DetailItem>
                    <DetailItem label="Jurusan">
                        <Link
                            href={`/master/departments/${record.school_class.department.id}`}
                        >
                            <Badge variant="outline">
                                {record.school_class.department.code} —{' '}
                                {record.school_class.department.name}
                            </Badge>
                        </Link>
                    </DetailItem>
                </dl>
            </section>

            <section>
                <p className="mb-2 text-sm font-medium">Akun Login</p>
                <dl className="grid gap-4 sm:grid-cols-2">
                    <DetailItem label="Email">{record.user.email}</DetailItem>
                    <DetailItem label="Status">
                        {record.user.is_active ? (
                            <Badge variant="secondary">Aktif</Badge>
                        ) : (
                            <Badge variant="outline">Nonaktif</Badge>
                        )}
                    </DetailItem>
                    <DetailItem label="Terakhir Masuk">
                        {formatDate(record.user.last_login_at)}
                    </DetailItem>
                </dl>
            </section>
        </div>
    );
}
