import { SchoolClassForm } from '@/components/master/school-class-form';
import type { SchoolClassValues } from '@/components/master/school-class-form';
import AppLayout from '@/layouts/app-layout';
import type { SelectOption } from '@/types/table';

type Props = {
    schoolClass: SchoolClassValues & { id: string };
    departments: SelectOption[];
};

export default function ClassEdit({ schoolClass, departments }: Props) {
    return (
        <AppLayout title="Ubah Kelas" description={schoolClass.name}>
            <SchoolClassForm
                action={`/master/classes/${schoolClass.id}`}
                method="put"
                departments={departments}
                values={schoolClass}
                submitLabel="Simpan Perubahan"
            />
        </AppLayout>
    );
}
