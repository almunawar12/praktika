import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';

export type TeacherRecord = {
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

function formatDate(iso: string | null): string {
    if (iso === null) {
        return 'Belum pernah masuk';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export function TeacherDetail({ record }: { record: TeacherRecord }) {
    return (
        <div className="grid gap-6">
            <section>
                <p className="mb-2 text-sm font-medium">Data Guru</p>
                <dl className="grid gap-4 sm:grid-cols-2">
                    <DetailItem label="NIP">{record.nip}</DetailItem>
                    <DetailItem label="Nama">{record.name}</DetailItem>
                    <DetailItem label="No HP">{record.phone ?? '—'}</DetailItem>
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
