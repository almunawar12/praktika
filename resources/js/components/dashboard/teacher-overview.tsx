import { Link } from '@inertiajs/react';
import { ArrowRight, MapPin, TriangleAlert } from 'lucide-react';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { TeacherOverview as TeacherOverviewData } from '@/types/dashboard';

export function TeacherOverview({
    overview,
}: {
    overview: TeacherOverviewData;
}) {
    return (
        <div className="grid gap-6 md:grid-cols-2">
            <Card className="flex flex-col">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <TriangleAlert
                            className="size-4 text-amber-600 dark:text-amber-400"
                            aria-hidden="true"
                        />
                        Siswa Perlu Perhatian
                    </CardTitle>
                </CardHeader>
                <CardContent className="flex flex-1 flex-col gap-3">
                    {overview.studentsNeedingAttention.length === 0 ? (
                        <p className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                            Tidak ada siswa yang perlu perhatian khusus.
                        </p>
                    ) : (
                        overview.studentsNeedingAttention.map((row) => (
                            <div
                                key={row.placementId}
                                className="flex items-center justify-between gap-3 border-b pb-3 last:border-0 last:pb-0"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {row.student}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {row.company}
                                    </p>
                                </div>
                                <div className="shrink-0 text-right text-xs text-muted-foreground">
                                    <p>{row.alphaCount}x alpha</p>
                                    <p>
                                        {row.journalPercentage.toFixed(0)}%
                                        jurnal
                                    </p>
                                </div>
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>

            <Card className="flex flex-col">
                <CardHeader>
                    <CardTitle className="flex items-center gap-2">
                        <MapPin
                            className="size-4 text-emerald-600 dark:text-emerald-400"
                            aria-hidden="true"
                        />
                        Jadwal Kunjungan
                    </CardTitle>
                </CardHeader>
                <CardContent className="flex flex-1 flex-col gap-3">
                    {overview.upcomingVisits.length === 0 ? (
                        <p className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                            Semua penempatan sudah dikunjungi 30 hari terakhir.
                        </p>
                    ) : (
                        overview.upcomingVisits.map((row) => (
                            <div
                                key={row.placementId}
                                className="flex items-center justify-between gap-3 border-b pb-3 last:border-0 last:pb-0"
                            >
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-medium">
                                        {row.student}
                                    </p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {row.company}
                                    </p>
                                </div>
                                <Link
                                    href={`/monitorings/create?placement_id=${row.placementId}`}
                                    className="shrink-0 text-xs text-primary hover:underline"
                                >
                                    Catat Kunjungan
                                </Link>
                            </div>
                        ))
                    )}
                </CardContent>
                {overview.upcomingVisits.length > 0 ? (
                    <CardFooter>
                        <Link
                            href="/monitorings"
                            className="flex items-center gap-1 text-sm text-primary hover:underline"
                        >
                            Lihat semua monitoring
                            <ArrowRight className="size-4" aria-hidden="true" />
                        </Link>
                    </CardFooter>
                ) : null}
            </Card>
        </div>
    );
}
