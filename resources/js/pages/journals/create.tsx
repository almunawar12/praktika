import { CircleAlert } from 'lucide-react';
import { JournalForm } from '@/components/journal/journal-form';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import AppLayout from '@/layouts/app-layout';

type Props = {
    placement: { company: string; start_date: string; end_date: string } | null;
};

export default function JournalCreate({ placement }: Props) {
    if (placement === null) {
        return (
            <AppLayout
                title="Isi Jurnal"
                description="Jurnal harian kegiatan PKL"
            >
                <Alert>
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>Belum ada PKL aktif</AlertTitle>
                    <AlertDescription>
                        Jurnal dapat diisi setelah Admin PKL mengaktifkan
                        penempatan Anda.
                    </AlertDescription>
                </Alert>
            </AppLayout>
        );
    }

    return (
        <AppLayout title="Isi Jurnal" description={placement.company}>
            <JournalForm
                action="/journals"
                method="post"
                period={placement}
                submitLabel="Kirim untuk Direview"
            />
        </AppLayout>
    );
}
