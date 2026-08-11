import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export type StudentSummary = {
    studentName: string;
    nis: string;
    className: string;
    companyName: string;
    teacherName: string;
    period: string;
    attendancePresent: number;
    attendancePermit: number;
    attendanceSick: number;
    attendanceAbsent: number;
    attendanceRate: string;
    journalTotal: number;
    journalApproved: number;
    journalPending: number;
    journalRejected: number;
    industryScore: string | null;
    teacherScore: string | null;
    finalScore: string | null;
    grade: string | null;
    assessmentStatus: string;
    monitorings: {
        visit_date: string;
        note: string;
        recommendation: string | null;
    }[];
};

function Stat({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="flex items-baseline justify-between gap-4 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

/**
 * Rekap Siswa on screen: the same sections the PDF prints, so what a user reads
 * here is what they get when they download it.
 */
export function StudentSummaryCard({ summary }: { summary: StudentSummary }) {
    return (
        <div className="grid gap-4">
            <Card>
                <CardHeader>
                    <CardTitle>
                        {summary.studentName} · {summary.nis} ·{' '}
                        {summary.className}
                    </CardTitle>
                </CardHeader>
                <CardContent className="grid gap-1 text-sm text-muted-foreground">
                    <span>{summary.companyName}</span>
                    <span>Guru pembimbing: {summary.teacherName}</span>
                    <span>Periode: {summary.period}</span>
                </CardContent>
            </Card>

            <div className="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Absensi</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        <Stat label="Hadir" value={summary.attendancePresent} />
                        <Stat label="Izin" value={summary.attendancePermit} />
                        <Stat label="Sakit" value={summary.attendanceSick} />
                        <Stat label="Alpa" value={summary.attendanceAbsent} />
                        <Stat
                            label="Kehadiran"
                            value={summary.attendanceRate}
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Jurnal</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        <Stat label="Total" value={summary.journalTotal} />
                        <Stat
                            label="Disetujui"
                            value={summary.journalApproved}
                        />
                        <Stat label="Pending" value={summary.journalPending} />
                        <Stat label="Ditolak" value={summary.journalRejected} />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">Nilai</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-2">
                        <Stat
                            label="Industri"
                            value={summary.industryScore ?? '-'}
                        />
                        <Stat
                            label="Guru"
                            value={summary.teacherScore ?? '-'}
                        />
                        <Stat label="Akhir" value={summary.finalScore ?? '-'} />
                        <Stat label="Grade" value={summary.grade ?? '-'} />
                        <Stat label="Status" value={summary.assessmentStatus} />
                    </CardContent>
                </Card>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        Kunjungan Monitoring
                    </CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3">
                    {summary.monitorings.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Belum ada kunjungan pada periode ini.
                        </p>
                    ) : (
                        summary.monitorings.map((visit) => (
                            <div
                                key={`${visit.visit_date}-${visit.note}`}
                                className="grid gap-1 border-b pb-3 last:border-0 last:pb-0"
                            >
                                <span className="text-sm font-medium">
                                    {visit.visit_date}
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {visit.note}
                                </span>
                                {visit.recommendation !== null && (
                                    <span className="text-sm text-muted-foreground">
                                        Rekomendasi: {visit.recommendation}
                                    </span>
                                )}
                            </div>
                        ))
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
