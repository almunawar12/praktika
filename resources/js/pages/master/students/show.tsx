import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';

type Props = {
    student: {
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

export default function StudentShow({ student }: Props) {
    const { can } = usePermission();

    return (
        <AppLayout title={student.name} description="Detail siswa">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/master/students">Kembali</Link>
                    </Button>
                    {can('students.update') ? (
                        <Button asChild>
                            <Link href={`/master/students/${student.id}/edit`}>
                                <Pencil className="size-4" aria-hidden="true" />
                                Ubah
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Siswa</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <DetailItem label="NIS">
                                    {student.nis}
                                </DetailItem>
                                <DetailItem label="NISN">
                                    {student.nisn}
                                </DetailItem>
                                <DetailItem label="Nama">
                                    {student.name}
                                </DetailItem>
                                <DetailItem label="No HP">
                                    {student.phone ?? '—'}
                                </DetailItem>
                                <DetailItem label="Kelas">
                                    <Link
                                        href={`/master/classes/${student.school_class.id}`}
                                    >
                                        <Badge variant="outline">
                                            {student.school_class.name}
                                        </Badge>
                                    </Link>
                                </DetailItem>
                                <DetailItem label="Jurusan">
                                    <Link
                                        href={`/master/departments/${student.school_class.department.id}`}
                                    >
                                        <Badge variant="outline">
                                            {
                                                student.school_class.department
                                                    .code
                                            }{' '}
                                            —{' '}
                                            {
                                                student.school_class.department
                                                    .name
                                            }
                                        </Badge>
                                    </Link>
                                </DetailItem>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Akun Login</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <DetailItem label="Email">
                                    {student.user.email}
                                </DetailItem>
                                <DetailItem label="Status">
                                    {student.user.is_active ? (
                                        <Badge variant="secondary">Aktif</Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            Nonaktif
                                        </Badge>
                                    )}
                                </DetailItem>
                                <DetailItem label="Terakhir Masuk">
                                    {formatDate(student.user.last_login_at)}
                                </DetailItem>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
