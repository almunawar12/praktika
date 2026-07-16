import { PlacementForm } from '@/components/pkl/placement-form';
import AppLayout from '@/layouts/app-layout';
import type { SelectOption } from '@/types/table';

type Props = {
    students: SelectOption[];
    companies: SelectOption[];
    teachers: SelectOption[];
};

export default function PlacementCreate({
    students,
    companies,
    teachers,
}: Props) {
    return (
        <AppLayout
            title="Tambah Penempatan"
            description="Assign siswa ke perusahaan dan guru pembimbing"
        >
            <PlacementForm
                action="/pkl/placements"
                method="post"
                students={students}
                companies={companies}
                teachers={teachers}
                submitLabel="Simpan sebagai Draft"
            />
        </AppLayout>
    );
}
