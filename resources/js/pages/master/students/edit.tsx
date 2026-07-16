import { StudentForm } from '@/components/master/student-form';
import type { StudentValues } from '@/components/master/student-form';
import AppLayout from '@/layouts/app-layout';
import type { SelectOption } from '@/types/table';

type Props = {
    student: StudentValues & { id: string };
    classes: SelectOption[];
};

export default function StudentEdit({ student, classes }: Props) {
    return (
        <AppLayout title="Ubah Siswa" description={student.name}>
            <StudentForm
                action={`/master/students/${student.id}`}
                method="put"
                classes={classes}
                values={student}
                submitLabel="Simpan Perubahan"
            />
        </AppLayout>
    );
}
