import { Link } from '@inertiajs/react';
import { CircleAlert } from 'lucide-react';
import { MonitoringForm } from '@/components/monitoring/monitoring-form';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { SelectOption } from '@/types/table';

type Props = {
    placements: SelectOption[];
    needingVisit: { id: string; label: string }[];
};

export default function MonitoringCreate({ placements, needingVisit }: Props) {
    if (placements.length === 0) {
        return (
            <AppLayout
                title="Catat Kunjungan"
                description="Laporan kunjungan guru"
            >
                <Alert>
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>Belum ada siswa bimbingan</AlertTitle>
                    <AlertDescription>
                        Kunjungan dapat dicatat setelah Anda ditugaskan sebagai
                        pembimbing pada penempatan PKL yang aktif atau sudah
                        selesai.
                    </AlertDescription>
                </Alert>
            </AppLayout>
        );
    }

    return (
        <AppLayout
            title="Catat Kunjungan"
            description="Laporan kunjungan ke lokasi PKL"
        >
            <div className="flex flex-col gap-4">
                {needingVisit.length > 0 ? (
                    <Alert>
                        <CircleAlert className="size-4" aria-hidden="true" />
                        <AlertTitle>
                            {needingVisit.length} penempatan belum dikunjungi 30
                            hari terakhir
                        </AlertTitle>
                        <AlertDescription>
                            <ul className="mt-1 flex flex-wrap gap-1.5">
                                {needingVisit.map((item) => (
                                    <li key={item.id}>
                                        <Badge variant="outline">
                                            {item.label}
                                        </Badge>
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                ) : null}

                <MonitoringForm
                    action="/monitorings"
                    method="post"
                    placements={placements}
                    submitLabel="Simpan Laporan"
                />

                <p className="text-xs text-muted-foreground">
                    Butuh melihat daftar bimbingan?{' '}
                    <Link
                        href="/pkl/placements"
                        className="underline-offset-4 hover:underline"
                    >
                        Buka Penempatan
                    </Link>
                    .
                </p>
            </div>
        </AppLayout>
    );
}
