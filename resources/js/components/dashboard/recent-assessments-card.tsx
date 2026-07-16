import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { RecentAssessment } from '@/types/dashboard';

export function RecentAssessmentsCard({
    items,
}: {
    items: RecentAssessment[];
}) {
    return (
        <Card className="flex flex-col">
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Penilaian Terbaru</CardTitle>
                <Button variant="outline" size="sm" asChild>
                    <Link href="/assessments">Lihat Semua</Link>
                </Button>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-4">
                {items.length === 0 ? (
                    <p className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                        Belum ada penilaian masuk.
                    </p>
                ) : (
                    items.map((assessment) => (
                        <div
                            key={assessment.id}
                            className="flex items-center gap-3"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {assessment.student}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {assessment.company}
                                </p>
                            </div>
                            <Badge variant="secondary">{assessment.type}</Badge>
                            <span className="w-8 text-right text-sm font-semibold tabular-nums">
                                {assessment.score ?? '-'}
                            </span>
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}
