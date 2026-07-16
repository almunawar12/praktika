import { router } from '@inertiajs/react';
import { CircleAlert, LoaderCircle, Lock, Send } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ScoreInput } from '@/components/assessment/score-input';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

type Criteria = { code: string; label: string };

type Props = {
    placement: { id: string; student: string; nis: string; company: string };
    type: { value: string; label: string };
    criteria: Criteria[];
    assessment: {
        id: string;
        note: string | null;
        average_score: string | null;
        is_submitted: boolean;
        scores: Record<string, number>;
    } | null;
};

export default function AssessmentEdit({
    placement,
    type,
    criteria,
    assessment,
}: Props) {
    const [scores, setScores] = useState<Record<string, number | ''>>(() =>
        Object.fromEntries(
            criteria.map((c) => [c.code, assessment?.scores[c.code] ?? '']),
        ),
    );
    const [note, setNote] = useState(assessment?.note ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const locked = assessment?.is_submitted ?? false;

    // Shown live so the assessor sees the number their marks add up to before
    // committing — the same average the final score is computed from.
    const average = useMemo(() => {
        const values = Object.values(scores).filter(
            (v): v is number => typeof v === 'number',
        );

        if (values.length !== criteria.length) {
            return null;
        }

        return (values.reduce((a, b) => a + b, 0) / values.length).toFixed(2);
    }, [scores, criteria.length]);

    const submit = (send: boolean) => {
        setProcessing(true);
        router.put(
            `/assessments/${placement.id}/${type.value}`,
            { scores, note, submit: send },
            {
                preserveScroll: true,
                onError: setErrors,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <AppLayout
            title={type.label}
            description={`${placement.student} — ${placement.company}`}
        >
            <div className="mx-auto flex w-full max-w-2xl flex-col gap-4">
                {locked ? (
                    <Alert>
                        <Lock className="size-4" aria-hidden="true" />
                        <AlertTitle>Penilaian sudah dikirim</AlertTitle>
                        <AlertDescription>
                            Penilaian yang sudah dikirim tidak dapat diubah.
                            Hubungi Admin PKL jika ada yang perlu diperbaiki.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <Card>
                    <CardHeader>
                        <CardTitle className="flex flex-wrap items-center justify-between gap-2">
                            <span>Kriteria Penilaian</span>
                            {average !== null ? (
                                <Badge
                                    variant="secondary"
                                    className="tabular-nums"
                                >
                                    Rata-rata {average}
                                </Badge>
                            ) : null}
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5">
                        {criteria.map((c) => (
                            <ScoreInput
                                key={c.code}
                                code={c.code}
                                label={c.label}
                                value={scores[c.code] ?? ''}
                                disabled={locked}
                                error={errors[`scores.${c.code}`]}
                                onChange={(value) =>
                                    setScores((prev) => ({
                                        ...prev,
                                        [c.code]: value,
                                    }))
                                }
                            />
                        ))}

                        <div className="grid gap-2">
                            <Label htmlFor="note">Catatan</Label>
                            <Textarea
                                id="note"
                                rows={3}
                                maxLength={2000}
                                value={note}
                                disabled={locked}
                                onChange={(event) =>
                                    setNote(event.target.value)
                                }
                                placeholder="Opsional. Masukan untuk siswa."
                                aria-invalid={Boolean(errors.note)}
                            />
                            {errors.note ? (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {errors.note}
                                </p>
                            ) : null}
                        </div>

                        {!locked ? (
                            <>
                                <Alert>
                                    <CircleAlert
                                        className="size-4"
                                        aria-hidden="true"
                                    />
                                    <AlertDescription>
                                        Simpan draft jika belum selesai. Setelah
                                        dikirim, penilaian terkunci dan nilai
                                        akhir dihitung otomatis begitu kedua
                                        penilaian masuk.
                                    </AlertDescription>
                                </Alert>

                                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() => submit(false)}
                                    >
                                        Simpan Draft
                                    </Button>
                                    <Button
                                        disabled={processing}
                                        onClick={() => submit(true)}
                                    >
                                        {processing ? (
                                            <LoaderCircle
                                                className="size-4 animate-spin"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <Send
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        )}
                                        Kirim Penilaian
                                    </Button>
                                </div>
                            </>
                        ) : null}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
