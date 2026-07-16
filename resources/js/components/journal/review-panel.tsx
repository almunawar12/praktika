import { Form } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    journalId: string;
    /** Which stage this reviewer owns. */
    stage: 'industry' | 'teacher';
};

const TITLES = {
    industry: 'Review Pembimbing Industri',
    teacher: 'Review Guru Pembimbing',
} as const;

export function ReviewPanel({ journalId, stage }: Props) {
    const [decision, setDecision] = useState<'approved' | 'rejected'>(
        'approved',
    );

    return (
        <Card className="max-w-2xl">
            <CardHeader>
                <CardTitle>{TITLES[stage]}</CardTitle>
            </CardHeader>
            <CardContent>
                <Form
                    action={`/journals/${journalId}/review/${stage}`}
                    method="put"
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="decision"
                                value={decision}
                            />

                            <div className="grid gap-2">
                                <Label htmlFor="note">
                                    Catatan
                                    {decision === 'rejected' ? (
                                        <span
                                            className="text-destructive"
                                            aria-hidden="true"
                                        >
                                            *
                                        </span>
                                    ) : null}
                                </Label>
                                <Textarea
                                    id="note"
                                    name="note"
                                    rows={3}
                                    maxLength={1000}
                                    placeholder={
                                        decision === 'rejected'
                                            ? 'Wajib: jelaskan apa yang perlu diperbaiki siswa.'
                                            : 'Opsional'
                                    }
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

                            <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="text-destructive"
                                    disabled={processing}
                                    onClick={() => setDecision('rejected')}
                                >
                                    Tolak
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    onClick={() => setDecision('approved')}
                                >
                                    Setujui
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </CardContent>
        </Card>
    );
}
