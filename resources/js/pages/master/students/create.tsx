import { StudentForm } from '@/components/master/student-form';
import AppLayout from '@/layouts/app-layout';
import type { SelectOption } from '@/types/table';

export default function StudentCreate({
    classes,
}: {
    classes: SelectOption[];
}) {
    return (
        <AppLayout
            title="Tambah Siswa"
            description="Buat data siswa dan akun loginnya"
        >
            <StudentForm
                action="/master/students"
                method="post"
                classes={classes}
                submitLabel="Simpan"
            />
        </AppLayout>
    );
}
