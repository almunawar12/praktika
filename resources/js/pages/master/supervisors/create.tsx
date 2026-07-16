import { SupervisorForm } from '@/components/master/supervisor-form';
import AppLayout from '@/layouts/app-layout';
import type { SelectOption } from '@/types/table';

export default function SupervisorCreate({
    companies,
}: {
    companies: SelectOption[];
}) {
    return (
        <AppLayout
            title="Tambah Pembimbing Industri"
            description="Buat data pembimbing dan akun loginnya"
        >
            <SupervisorForm
                action="/master/supervisors"
                method="post"
                companies={companies}
                submitLabel="Simpan"
            />
        </AppLayout>
    );
}
