import { DepartmentForm } from '@/components/master/department-form';
import AppLayout from '@/layouts/app-layout';

export default function DepartmentCreate() {
    return (
        <AppLayout title="Tambah Jurusan" description="Buat jurusan baru">
            <DepartmentForm
                action="/master/departments"
                method="post"
                submitLabel="Simpan"
            />
        </AppLayout>
    );
}
