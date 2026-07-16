import { TeacherForm } from '@/components/master/teacher-form';
import AppLayout from '@/layouts/app-layout';

export default function TeacherCreate() {
    return (
        <AppLayout
            title="Tambah Guru"
            description="Buat data guru dan akun loginnya"
        >
            <TeacherForm
                action="/master/teachers"
                method="post"
                submitLabel="Simpan"
            />
        </AppLayout>
    );
}
