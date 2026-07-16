import { DepartmentForm } from '@/components/master/department-form';
import type { DepartmentValues } from '@/components/master/department-form';
import AppLayout from '@/layouts/app-layout';

export default function DepartmentEdit({
    department,
}: {
    department: DepartmentValues;
}) {
    return (
        <AppLayout title="Ubah Jurusan" description={department.name}>
            <DepartmentForm
                action={`/master/departments/${department.id}`}
                method="put"
                values={department}
                submitLabel="Simpan Perubahan"
            />
        </AppLayout>
    );
}
