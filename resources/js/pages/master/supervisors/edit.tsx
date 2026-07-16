import { SupervisorForm } from '@/components/master/supervisor-form';
import type { SupervisorValues } from '@/components/master/supervisor-form';
import AppLayout from '@/layouts/app-layout';
import type { SelectOption } from '@/types/table';

type Props = {
    supervisor: SupervisorValues & { id: string };
    companies: SelectOption[];
};

export default function SupervisorEdit({ supervisor, companies }: Props) {
    return (
        <AppLayout
            title="Ubah Pembimbing Industri"
            description={supervisor.name}
        >
            <SupervisorForm
                action={`/master/supervisors/${supervisor.id}`}
                method="put"
                companies={companies}
                values={supervisor}
                submitLabel="Simpan Perubahan"
            />
        </AppLayout>
    );
}
