import { Link } from '@inertiajs/react';
import { CircleAlert, FileText, Paperclip, Pencil } from 'lucide-react';
import { useState } from 'react';
import { ReviewBadge } from '@/components/journal/review-badges';
import type { ReviewStatus } from '@/components/journal/review-badges';
import { ReviewPanel } from '@/components/journal/review-panel';
import { DeleteDialog } from '@/components/shared/delete-dialog';
import { DetailItem } from '@/components/shared/field';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

type Attachment = {
    id: string;
    original_name: string;
    caption: string | null;
    size: number;
    is_image: boolean;
    url: string;
};

type Props = {
    journal: {
        id: string;
        date: string;
        activity: string;
        work_result: string;
        obstacle: string | null;
        status: ReviewStatus;
        industry_status: ReviewStatus;
        industry_note: string | null;
        teacher_status: ReviewStatus;
        teacher_note: string | null;
        is_editable: boolean;
        attachments: Attachment[];
        placement: {
            id: string;
            student: { id: string; name: string; nis: string };
            company: { id: string; name: string };
        };
        industry_reviewer: { id: string; name: string } | null;
        teacher_reviewer: { id: string; name: string } | null;
    };
    can: {
        update: boolean;
        delete: boolean;
        reviewAsIndustry: boolean;
        reviewAsTeacher: boolean;
    };
};

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'full' }).format(
        new Date(iso),
    );
}

export default function JournalShow({ journal, can }: Props) {
    const [confirmingDelete, setConfirmingDelete] = useState(false);

    return (
        <AppLayout title="Jurnal Harian" description={formatDate(journal.date)}>
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/journals">Kembali</Link>
                    </Button>

                    {can.update ? (
                        <Button asChild>
                            <Link href={`/journals/${journal.id}/edit`}>
                                <Pencil className="size-4" aria-hidden="true" />
                                {journal.status === 'rejected'
                                    ? 'Perbaiki'
                                    : 'Ubah'}
                            </Link>
                        </Button>
                    ) : null}

                    {can.delete ? (
                        <Button
                            variant="ghost"
                            className="text-destructive"
                            onClick={() => setConfirmingDelete(true)}
                        >
                            Hapus
                        </Button>
                    ) : null}

                    <div className="ml-auto flex flex-wrap gap-1.5">
                        <ReviewBadge
                            status={journal.industry_status}
                            prefix="Industri"
                        />
                        <ReviewBadge
                            status={journal.teacher_status}
                            prefix="Guru"
                        />
                    </div>
                </div>

                {journal.industry_status === 'rejected' &&
                journal.industry_note ? (
                    <Alert variant="destructive">
                        <CircleAlert className="size-4" aria-hidden="true" />
                        <AlertTitle>Ditolak pembimbing industri</AlertTitle>
                        <AlertDescription>
                            {journal.industry_note}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {journal.teacher_status === 'rejected' &&
                journal.teacher_note ? (
                    <Alert variant="destructive">
                        <CircleAlert className="size-4" aria-hidden="true" />
                        <AlertTitle>Ditolak guru pembimbing</AlertTitle>
                        <AlertDescription>
                            {journal.teacher_note}
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Isi Jurnal</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4">
                                <DetailItem label="Uraian Kegiatan">
                                    <span className="whitespace-pre-line">
                                        {journal.activity}
                                    </span>
                                </DetailItem>
                                <DetailItem label="Hasil Pekerjaan">
                                    <span className="whitespace-pre-line">
                                        {journal.work_result}
                                    </span>
                                </DetailItem>
                                <DetailItem label="Kendala">
                                    <span className="whitespace-pre-line">
                                        {journal.obstacle ?? '—'}
                                    </span>
                                </DetailItem>
                            </dl>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4">
                                <DetailItem label="Siswa">
                                    {journal.placement.student.name}
                                </DetailItem>
                                <DetailItem label="Perusahaan">
                                    {journal.placement.company.name}
                                </DetailItem>
                                <DetailItem label="Status Akhir">
                                    <ReviewBadge status={journal.status} />
                                </DetailItem>
                                {journal.industry_reviewer ? (
                                    <DetailItem label="Direview Industri oleh">
                                        {journal.industry_reviewer.name}
                                    </DetailItem>
                                ) : null}
                                {journal.teacher_reviewer ? (
                                    <DetailItem label="Direview Guru oleh">
                                        {journal.teacher_reviewer.name}
                                    </DetailItem>
                                ) : null}
                            </dl>
                        </CardContent>
                    </Card>
                </div>

                {journal.attachments.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Dokumentasi</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {journal.attachments.map((attachment) => (
                                <a
                                    key={attachment.id}
                                    href={attachment.url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="grid gap-1.5 rounded-lg border p-2 hover:bg-muted/50"
                                >
                                    {attachment.is_image ? (
                                        <img
                                            src={attachment.url}
                                            alt={
                                                attachment.caption ??
                                                attachment.original_name
                                            }
                                            className="aspect-[4/3] w-full rounded object-cover"
                                        />
                                    ) : (
                                        <span className="flex aspect-[4/3] w-full items-center justify-center rounded bg-muted">
                                            <FileText
                                                className="size-8 text-muted-foreground"
                                                aria-hidden="true"
                                            />
                                        </span>
                                    )}
                                    <span className="flex items-center gap-1 truncate text-xs text-muted-foreground">
                                        <Paperclip
                                            className="size-3 shrink-0"
                                            aria-hidden="true"
                                        />
                                        {attachment.original_name}
                                    </span>
                                </a>
                            ))}
                        </CardContent>
                    </Card>
                ) : null}

                {/* Which panel appears is decided by the server: it depends on the
                    review stage, not only on the user's permissions. */}
                {can.reviewAsIndustry ? (
                    <ReviewPanel journalId={journal.id} stage="industry" />
                ) : null}

                {can.reviewAsTeacher ? (
                    <ReviewPanel journalId={journal.id} stage="teacher" />
                ) : null}
            </div>

            <DeleteDialog
                open={confirmingDelete}
                onOpenChange={setConfirmingDelete}
                action={`/journals/${journal.id}`}
                title="Hapus jurnal ini?"
                description="Jurnal akan dihapus dan tanggal ini dapat diisi ulang."
            />
        </AppLayout>
    );
}
