import { Form, Link } from '@inertiajs/react';
import { Info, LoaderCircle, Paperclip, X } from 'lucide-react';
import { useRef, useState } from 'react';
import { Field } from '@/components/shared/field';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export type JournalValues = {
    id?: string;
    date: string;
    activity: string;
    work_result: string;
    obstacle: string | null;
};

type Props = {
    action: string;
    method: 'post' | 'put';
    values?: JournalValues;
    /** Only present on create — the date is fixed once the journal exists. */
    period?: { start_date: string; end_date: string };
    submitLabel: string;
    /** Shown when a revision will send the journal back for review. */
    resetsReview?: boolean;
};

export function JournalForm({
    action,
    method,
    values,
    period,
    submitLabel,
    resetsReview = false,
}: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [files, setFiles] = useState<File[]>([]);
    const isCreate = method === 'post';

    const today = new Date().toISOString().slice(0, 10);

    return (
        // The file input carries a name, so Inertia's Form reads it straight from the
        // DOM and switches to FormData on its own — no transform needed.
        <Form action={action} method={method} className="max-w-3xl">
            {({ processing, errors }) => (
                <Card>
                    <CardContent className="grid gap-4">
                        {resetsReview ? (
                            <Alert>
                                <Info className="size-4" aria-hidden="true" />
                                <AlertDescription>
                                    Menyimpan revisi akan mengirim jurnal ini
                                    kembali ke pembimbing industri untuk
                                    direview ulang dari awal.
                                </AlertDescription>
                            </Alert>
                        ) : null}

                        {isCreate ? (
                            <Field
                                id="date"
                                label="Tanggal"
                                error={errors.date}
                                required
                            >
                                <Input
                                    id="date"
                                    name="date"
                                    type="date"
                                    max={today}
                                    min={period?.start_date}
                                    defaultValue={values?.date ?? today}
                                    required
                                    className="sm:max-w-56"
                                    aria-invalid={Boolean(errors.date)}
                                />
                            </Field>
                        ) : null}

                        <Field
                            id="activity"
                            label="Uraian Kegiatan"
                            error={errors.activity}
                            required
                            hint="Jelaskan apa yang Anda kerjakan hari ini."
                        >
                            <Textarea
                                id="activity"
                                name="activity"
                                rows={4}
                                defaultValue={values?.activity}
                                required
                                minLength={10}
                                maxLength={2000}
                                aria-invalid={Boolean(errors.activity)}
                            />
                        </Field>

                        <Field
                            id="work_result"
                            label="Hasil Pekerjaan"
                            error={errors.work_result}
                            required
                            hint="Apa hasil konkret dari kegiatan tersebut."
                        >
                            <Textarea
                                id="work_result"
                                name="work_result"
                                rows={4}
                                defaultValue={values?.work_result}
                                required
                                minLength={10}
                                maxLength={2000}
                                aria-invalid={Boolean(errors.work_result)}
                            />
                        </Field>

                        <Field
                            id="obstacle"
                            label="Kendala"
                            error={errors.obstacle}
                            hint="Opsional. Kosongkan jika tidak ada kendala."
                        >
                            <Textarea
                                id="obstacle"
                                name="obstacle"
                                rows={3}
                                defaultValue={values?.obstacle ?? ''}
                                maxLength={2000}
                                aria-invalid={Boolean(errors.obstacle)}
                            />
                        </Field>

                        <Field
                            id="attachments"
                            label="Dokumentasi"
                            error={
                                errors.attachments ?? errors['attachments.0']
                            }
                            hint="Opsional. Maksimal 5 berkas, foto atau PDF, masing-masing maks 4 MB."
                        >
                            <Input
                                ref={fileInput}
                                id="attachments"
                                name="attachments[]"
                                type="file"
                                multiple
                                accept="image/*,application/pdf"
                                // State drives the preview list only; the submitted
                                // value comes from the input itself.
                                onChange={(event) =>
                                    setFiles(
                                        Array.from(event.target.files ?? []),
                                    )
                                }
                            />
                            {files.length > 0 ? (
                                <ul className="grid gap-1">
                                    {files.map((file) => (
                                        <li
                                            key={file.name}
                                            className="flex items-center gap-2 text-xs text-muted-foreground"
                                        >
                                            <Paperclip
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            {file.name}
                                        </li>
                                    ))}
                                    <li>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            // Clears the input too, not just the
                                            // preview — otherwise the files would
                                            // still be submitted.
                                            onClick={() => {
                                                setFiles([]);

                                                if (fileInput.current) {
                                                    fileInput.current.value =
                                                        '';
                                                }
                                            }}
                                        >
                                            <X
                                                className="size-3"
                                                aria-hidden="true"
                                            />
                                            Kosongkan pilihan
                                        </Button>
                                    </li>
                                </ul>
                            ) : null}
                        </Field>

                        <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                            <Button variant="outline" asChild>
                                <Link href="/journals">Batal</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? (
                                    <LoaderCircle
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                ) : null}
                                {submitLabel}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            )}
        </Form>
    );
}
