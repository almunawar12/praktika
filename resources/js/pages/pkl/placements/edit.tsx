import { PlacementForm } from '@/components/pkl/placement-form';
import type { PlacementValues } from '@/components/pkl/placement-form';
import AppLayout from '@/layouts/app-layout';
import type { SelectOption } from '@/types/table';

type Props = {
    placement: PlacementValues & { id: string };
    students: SelectOption[];
    companies: SelectOption[];
    teachers: SelectOption[];
};

export default function PlacementEdit({
    placement,
    students,
    companies,
    teachers,
}: Props) {
    return (
        <AppLayout
            title="Ubah Penempatan"
            description="Perbarui data penempatan PKL"
        >
            <PlacementForm
                action={`/pkl/placements/${placement.id}`}
                method="put"
                students={students}
                companies={companies}
                teachers={teachers}
                values={placement}
                submitLabel="Simpan Perubahan"
            />
        </AppLayout>
    );
}
