import { CompanyForm } from '@/components/master/company-form';
import AppLayout from '@/layouts/app-layout';

export default function CompanyCreate() {
    return (
        <AppLayout title="Tambah Perusahaan" description="Daftarkan DUDI baru">
            <CompanyForm
                action="/master/companies"
                method="post"
                submitLabel="Simpan"
            />
        </AppLayout>
    );
}
