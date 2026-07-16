import { TeacherForm } from '@/components/master/teacher-form';
import type { TeacherValues } from '@/components/master/teacher-form';
import AppLayout from '@/layouts/app-layout';

export default function TeacherEdit({
    teacher,
}: {
    teacher: TeacherValues & { id: string };
}) {
    return (
        <AppLayout title="Ubah Guru" description={teacher.name}>
            <TeacherForm
                action={`/master/teachers/${teacher.id}`}
                method="put"
                values={teacher}
                submitLabel="Simpan Perubahan"
            />
        </AppLayout>
    );
}
