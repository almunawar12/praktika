import { Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermission } from '@/hooks/use-permission';
import AppLayout from '@/layouts/app-layout';

type Props = {
    supervisor: {
        id: string;
        name: string;
        position: string | null;
        phone: string | null;
        user: {
            id: string;
            email: string;
            is_active: boolean;
            last_login_at: string | null;
        };
        company: { id: string; name: string; business_field: string };
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

export default function SupervisorShow({ supervisor }: Props) {
    const { can } = usePermission();

    return (
        <AppLayout
            title={supervisor.name}
            description="Detail pembimbing industri"
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/master/supervisors">Kembali</Link>
                    </Button>
                    {can('company-supervisors.update') ? (
                        <Button asChild>
                            <Link
                                href={`/master/supervisors/${supervisor.id}/edit`}
                            >
                                <Pencil className="size-4" aria-hidden="true" />
                                Ubah
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Data Pembimbing</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                <DetailItem label="Nama">
                                    {supervisor.name}
                                </DetailItem>
                                <DetailItem label="Jabatan">
                                    {supervisor.position ?? '—'}
                                </DetailItem>
                                <DetailItem label="No HP">
                                    {supervisor.phone ?? '—'}
                                </DetailItem>
                                <DetailItem label="Perusahaan">
                                    <Link
                                        href={`/master/companies/${supervisor.company.id}`}
                                    >
                                        <Badge variant="outline">
                                            {supervisor.company.name}
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
                                    {supervisor.user.email}
                                </DetailItem>
                                <DetailItem label="Status">
                                    {supervisor.user.is_active ? (
                                        <Badge variant="secondary">Aktif</Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            Nonaktif
                                        </Badge>
                                    )}
                                </DetailItem>
                                <DetailItem label="Terakhir Masuk">
                                    {formatDate(supervisor.user.last_login_at)}
                                </DetailItem>
                                <div className="sm:col-span-2">
                                    <DetailItem label="Cakupan Akses">
                                        <span className="text-xs font-normal text-muted-foreground">
                                            Hanya siswa PKL di{' '}
                                            {supervisor.company.name} — absensi
                                            dan jurnal mereka.
                                        </span>
                                    </DetailItem>
                                </div>
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
