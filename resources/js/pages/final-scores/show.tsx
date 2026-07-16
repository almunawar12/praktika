import { Link, router } from '@inertiajs/react';
import { Calculator, EyeOff, Send } from 'lucide-react';
import { useState } from 'react';
import { GradeBadge } from '@/components/assessment/grade-badge';
import type { Grade } from '@/components/assessment/grade-badge';
import { DetailItem } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

type Props = {
    score: {
        id: string;
        industry_score: string;
        teacher_score: string;
        industry_weight: string;
        teacher_weight: string;
        final_score: string;
        grade: Grade;
        published_at: string | null;
        is_published: boolean;
        placement: {
            id: string;
            start_date: string;
            end_date: string;
            student: { id: string; name: string; nis: string };
            company: { id: string; name: string };
            teacher: { id: string; name: string };
        };
        publisher: { id: string; name: string } | null;
    };
    can: { publish: boolean; unpublish: boolean; calculate: boolean };
};

function pct(weight: string): string {
    return `${Math.round(Number(weight) * 100)}%`;
}

export default function FinalScoreShow({ score, can }: Props) {
    const [busy, setBusy] = useState(false);

    const act = (method: 'post' | 'delete', url: string) => {
        setBusy(true);
        router[method](
            url,
            {},
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };

    return (
        <AppLayout
            title={`Nilai Akhir — ${score.placement.student.name}`}
            description={score.placement.company.name}
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href="/final-scores">Kembali</Link>
                    </Button>

                    {can.publish ? (
                        <Button
                            disabled={busy}
                            onClick={() =>
                                act('post', `/final-scores/${score.id}/publish`)
                            }
                        >
                            <Send className="size-4" aria-hidden="true" />
                            Publikasikan
                        </Button>
                    ) : null}

                    {can.unpublish ? (
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() =>
                                act(
                                    'delete',
                                    `/final-scores/${score.id}/publish`,
                                )
                            }
                        >
                            <EyeOff className="size-4" aria-hidden="true" />
                            Batalkan Publikasi
                        </Button>
                    ) : null}

                    {/* Only offered while unpublished — a published score keeps the
                        weights it was computed with. */}
                    {can.calculate && !score.is_published ? (
                        <Button
                            variant="ghost"
                            disabled={busy}
                            onClick={() =>
                                act(
                                    'post',
                                    `/final-scores/${score.placement.id}/calculate`,
                                )
                            }
                        >
                            <Calculator className="size-4" aria-hidden="true" />
                            Hitung Ulang
                        </Button>
                    ) : null}

                    <div className="ml-auto">
                        {score.is_published ? (
                            <Badge className="border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400">
                                Dipublikasikan
                            </Badge>
                        ) : (
                            <Badge variant="secondary">
                                Belum dipublikasikan
                            </Badge>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Perhitungan</CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-4">
                            {/* The weights shown are the ones stored with this score,
                                not today's setting — that is the whole point of the
                                snapshot (ADR-03). */}
                            <div className="grid gap-3">
                                <div className="flex items-baseline justify-between gap-4">
                                    <span className="text-sm">
                                        Penilaian Industri
                                        <span className="ml-1.5 text-xs text-muted-foreground">
                                            bobot {pct(score.industry_weight)}
                                        </span>
                                    </span>
                                    <span className="tabular-nums">
                                        {Number(score.industry_score).toFixed(
                                            2,
                                        )}
                                    </span>
                                </div>

                                <div className="flex items-baseline justify-between gap-4">
                                    <span className="text-sm">
                                        Penilaian Guru
                                        <span className="ml-1.5 text-xs text-muted-foreground">
                                            bobot {pct(score.teacher_weight)}
                                        </span>
                                    </span>
                                    <span className="tabular-nums">
                                        {Number(score.teacher_score).toFixed(2)}
                                    </span>
                                </div>

                                <div className="flex items-baseline justify-between gap-4 border-t pt-3">
                                    <span className="font-medium">
                                        Nilai Akhir
                                    </span>
                                    <span className="flex items-center gap-2">
                                        <span className="text-2xl font-semibold tabular-nums">
                                            {Number(score.final_score).toFixed(
                                                2,
                                            )}
                                        </span>
                                        <GradeBadge grade={score.grade} />
                                    </span>
                                </div>
                            </div>

                            <p className="text-xs text-muted-foreground">
                                {Number(score.industry_score).toFixed(2)} ×{' '}
                                {pct(score.industry_weight)} +{' '}
                                {Number(score.teacher_score).toFixed(2)} ×{' '}
                                {pct(score.teacher_weight)} ={' '}
                                {Number(score.final_score).toFixed(2)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <dl className="grid gap-4">
                                <DetailItem label="Siswa">
                                    {score.placement.student.name}
                                </DetailItem>
                                <DetailItem label="NIS">
                                    {score.placement.student.nis}
                                </DetailItem>
                                <DetailItem label="Perusahaan">
                                    {score.placement.company.name}
                                </DetailItem>
                                <DetailItem label="Guru Pembimbing">
                                    {score.placement.teacher.name}
                                </DetailItem>
                                {score.publisher && score.is_published ? (
                                    <DetailItem label="Dipublikasikan oleh">
                                        {score.publisher.name}
                                    </DetailItem>
                                ) : null}
                            </dl>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
