import { CompanyForm } from '@/components/master/company-form';
import type { CompanyValues } from '@/components/master/company-form';
import AppLayout from '@/layouts/app-layout';

export default function CompanyEdit({
    company,
}: {
    company: CompanyValues & { id: string };
}) {
    return (
        <AppLayout title="Ubah Perusahaan" description={company.name}>
            <CompanyForm
                action={`/master/companies/${company.id}`}
                method="put"
                values={company}
                submitLabel="Simpan Perubahan"
            />
        </AppLayout>
    );
}
