import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';

type Props = {
    teacher: {
        id: string;
        nip: string;
        name: string;
        phone: string | null;
        user: {
            id: string;
            email: string;
            is_active: boolean;
            last_login_at: string | null;
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

export default function TeacherShow({ teacher }: Props) {
    const { can } = usePermission();

    return (
        <AppLayout title={teacher.name} description="Detail guru pembimbing">
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/master/teachers">Kembali</Link>
                    </Button>
                    {can('teachers.update') ? (
                        <Button asChild>
                            <Link href={`/master/teachers/${teacher.id}/edit`}>
                                <Pencil className="size-4" aria-hidden="true" />
                                Ubah
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Guru</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <DetailItem label="NIP">
                                    {teacher.nip}
                                </DetailItem>
                                <DetailItem label="Nama">
                                    {teacher.name}
                                </DetailItem>
                                <DetailItem label="No HP">
                                    {teacher.phone ?? '—'}
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
                                    {teacher.user.email}
                                </DetailItem>
                                <DetailItem label="Status">
                                    {teacher.user.is_active ? (
                                        <Badge variant="secondary">Aktif</Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            Nonaktif
                                        </Badge>
                                    )}
                                </DetailItem>
                                <DetailItem label="Terakhir Masuk">
                                    {formatDate(teacher.user.last_login_at)}
                                </DetailItem>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
