import { JournalForm } from '@/components/journal/journal-form';
import type { JournalValues } from '@/components/journal/journal-form';
import AppLayout from '@/layouts/app-layout';

type Props = {
    journal: JournalValues & {
        id: string;
        industry_status: string;
        teacher_status: string;
        industry_note: string | null;
        teacher_note: string | null;
    };
};

export default function JournalEdit({ journal }: Props) {
    // Only a rejected journal is being revised; a pending one is just being finished.
    const isRevision =
        journal.industry_status === 'rejected' ||
        journal.teacher_status === 'rejected';

    return (
        <AppLayout
            title={isRevision ? 'Perbaiki Jurnal' : 'Ubah Jurnal'}
            description={new Intl.DateTimeFormat('id-ID', {
                dateStyle: 'full',
            }).format(new Date(journal.date))}
        >
            <JournalForm
                action={`/journals/${journal.id}`}
                method="put"
                values={journal}
                submitLabel={isRevision ? 'Kirim Revisi' : 'Simpan Perubahan'}
                resetsReview={isRevision}
            />
        </AppLayout>
    );
}
