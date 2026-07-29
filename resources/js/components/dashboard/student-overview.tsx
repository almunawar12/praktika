import { Link } from '@inertiajs/react';
import {
    Award,
    Building2,
    CalendarCheck,
    CircleAlert,
    NotebookPen,
} from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import type { StudentOverview as StudentOverviewData } from '@/types/dashboard';

function formatDate(iso: string): string {
    return new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium' }).format(
        new Date(iso),
    );
}

const statusLabel: Record<string, string> = {
    present: 'Hadir',
    permit: 'Izin',
    sick: 'Sakit',
    absent: 'Alpha',
};

export function StudentOverview({
    overview,
}: {
    overview: StudentOverviewData;
}) {
    const { todayStatus } = overview;
    const hasCheckedIn = todayStatus.checkInAt !== null;
    const hasCheckedOut = todayStatus.checkOutAt !== null;

    return (
        <div className="flex flex-col gap-6">
            <div className="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Status Absensi Hari Ini</CardTitle>
                        <CardDescription>
                            {todayStatus.status !== null
                                ? (statusLabel[todayStatus.status] ??
                                  todayStatus.status)
                                : 'Belum ada catatan absensi hari ini'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button asChild size="lg" className="w-full">
                            <Link href="/attendances/check-in">
                                {!hasCheckedIn
                                    ? 'Check In'
                                    : !hasCheckedOut
                                      ? 'Check Out'
                                      : 'Lihat Absensi'}
                            </Link>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Building2
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            Info Penempatan
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-1 text-sm">
                        <p className="font-medium">
                            {overview.placement.company}
                        </p>
                        <p className="text-muted-foreground">
                            {overview.placement.teacher}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {formatDate(overview.placement.startDate)} —{' '}
                            {formatDate(overview.placement.endDate)}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Progres PKL</CardTitle>
                        <CardDescription>
                            Hari ke-{overview.progress.daysElapsed} dari{' '}
                            {overview.progress.totalDays}
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Progress value={overview.progress.percentage} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Kelengkapan Jurnal</CardTitle>
                        <CardDescription>
                            {overview.journalCompletion.filled} dari{' '}
                            {overview.journalCompletion.workdaysElapsed} hari
                            kerja terisi
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Progress
                            value={overview.journalCompletion.percentage}
                        />
                    </CardContent>
                </Card>
            </div>

            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                {(
                    [
                        ['hadir', 'Hadir', overview.attendanceRecap.hadir],
                        ['izin', 'Izin', overview.attendanceRecap.izin],
                        ['sakit', 'Sakit', overview.attendanceRecap.sakit],
                        ['alpha', 'Alpha', overview.attendanceRecap.alpha],
                    ] as const
                ).map(([key, label, value]) => (
                    <Card key={key}>
                        <CardContent className="flex flex-col items-center gap-1 py-4">
                            <CalendarCheck
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <p className="text-2xl font-semibold tabular-nums">
                                {value}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {label}
                            </p>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <div className="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <NotebookPen
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            Jurnal Hari Ini
                        </CardTitle>
                        <CardDescription>
                            {overview.journalToday.filled
                                ? 'Sudah diisi hari ini'
                                : 'Belum diisi hari ini'}
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="flex gap-2">
                            <Badge variant="secondary">
                                {overview.journalStatus.pending} pending
                            </Badge>
                            <Badge variant="secondary">
                                {overview.journalStatus.approved} approved
                            </Badge>
                            <Badge variant="secondary">
                                {overview.journalStatus.rejected} rejected
                            </Badge>
                        </div>
                        {!overview.journalToday.filled ? (
                            <Button asChild variant="outline">
                                <Link href="/journals/create">
                                    Isi Jurnal Hari Ini
                                </Link>
                            </Button>
                        ) : null}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Award
                                className="size-4 text-muted-foreground"
                                aria-hidden="true"
                            />
                            Nilai Akhir
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {overview.finalScore !== null ? (
                            <div className="flex items-baseline gap-3">
                                <p className="text-3xl font-semibold tabular-nums">
                                    {overview.finalScore.score.toFixed(1)}
                                </p>
                                <Badge>{overview.finalScore.grade}</Badge>
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                Nilai belum dipublikasikan.
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>

            {overview.rejectedJournals.length > 0 ? (
                <Alert variant="destructive">
                    <CircleAlert className="size-4" aria-hidden="true" />
                    <AlertTitle>Jurnal Ditolak — Perlu Revisi</AlertTitle>
                    <AlertDescription>
                        <ul className="flex flex-col gap-1">
                            {overview.rejectedJournals.map((journal) => (
                                <li key={journal.id}>
                                    <Link
                                        href={`/journals/${journal.id}/edit`}
                                        className="underline"
                                    >
                                        {formatDate(journal.date)}
                                    </Link>
                                    {journal.note !== null
                                        ? ` — ${journal.note}`
                                        : null}
                                </li>
                            ))}
                        </ul>
                    </AlertDescription>
                </Alert>
            ) : null}
        </div>
    );
}
