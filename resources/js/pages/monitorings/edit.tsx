import { MonitoringForm } from '@/components/monitoring/monitoring-form';
import type { MonitoringValues } from '@/components/monitoring/monitoring-form';
import AppLayout from '@/layouts/app-layout';

type Props = {
    monitoring: MonitoringValues & { id: string };
    placementLabel: string;
};

export default function MonitoringEdit({ monitoring, placementLabel }: Props) {
    return (
        <AppLayout title="Ubah Laporan Kunjungan" description={placementLabel}>
            <MonitoringForm
                action={`/monitorings/${monitoring.id}`}
                method="put"
                placementLabel={placementLabel}
                values={monitoring}
                submitLabel="Simpan Perubahan"
            />
        </AppLayout>
    );
}
