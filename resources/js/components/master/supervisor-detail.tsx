import { Link } from '@inertiajs/react';
import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';

export type SupervisorRecord = {
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

function formatDate(iso: string | null): string {
    if (iso === null) {
        return 'Belum pernah masuk';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export function SupervisorDetail({ record }: { record: SupervisorRecord }) {
    return (
        <div className="grid gap-6">
            <section>
                <p className="mb-2 text-sm font-medium">Data Pembimbing</p>
                <dl className="grid gap-4 sm:grid-cols-2">
                    <DetailItem label="Nama">{record.name}</DetailItem>
                    <DetailItem label="Jabatan">
                        {record.position ?? '—'}
                    </DetailItem>
                    <DetailItem label="No HP">{record.phone ?? '—'}</DetailItem>
                    <DetailItem label="Perusahaan">
                        <Link href={`/master/companies/${record.company.id}`}>
                            <Badge variant="outline">
                                {record.company.name}
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
                    <div className="sm:col-span-2">
                        <DetailItem label="Cakupan Akses">
                            <span className="text-xs font-normal text-muted-foreground">
                                Hanya siswa PKL di {record.company.name} —
                                absensi dan jurnal mereka.
                            </span>
                        </DetailItem>
                    </div>
                </dl>
            </section>
        </div>
    );
}
