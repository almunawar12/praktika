import { SchoolClassForm } from '@/components/master/school-class-form';
import AppLayout from '@/layouts/app-layout';
import type { SelectOption } from '@/types/table';

export default function ClassCreate({
    departments,
}: {
    departments: SelectOption[];
}) {
    return (
        <AppLayout title="Tambah Kelas" description="Buat kelas baru">
            <SchoolClassForm
                action="/master/classes"
                method="post"
                departments={departments}
                submitLabel="Simpan"
            />
        </AppLayout>
    );
}
