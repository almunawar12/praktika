import { router } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { Field } from '@/components/shared/field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';

type Props = {
    weights: { industry: number; teacher: number };
    editable: boolean;
};

export default function SettingsIndex({ weights, editable }: Props) {
    // Held as whole percentages for editing; the two always complete 100%.
    const [industry, setIndustry] = useState(
        Math.round(weights.industry * 100),
    );
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const teacher = 100 - industry;

    const setFromIndustry = (value: number) => {
        const clamped = Math.max(
            0,
            Math.min(100, Number.isNaN(value) ? 0 : value),
        );
        setIndustry(clamped);
    };

    const save = () => {
        setSaving(true);
        setError(null);
        router.put(
            '/system/settings',
            {
                industry_weight: industry / 100,
                teacher_weight: teacher / 100,
            },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(
                        errors.teacher_weight ??
                            errors.industry_weight ??
                            'Gagal menyimpan.',
                    ),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <AppLayout title="Pengaturan" description="Bobot penilaian akhir PKL">
            <Card className="max-w-xl">
                <CardHeader>
                    <CardTitle>Bobot Nilai Akhir</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-4">
                    <p className="text-sm text-muted-foreground">
                        Nilai akhir = (nilai industri × bobot industri) + (nilai
                        guru × bobot guru). Keduanya selalu berjumlah 100%.
                        Perubahan hanya berlaku untuk nilai yang dihitung
                        setelahnya.
                    </p>

                    <div className="grid grid-cols-2 gap-4">
                        <Field id="industry" label="Bobot Industri (%)">
                            <Input
                                id="industry"
                                type="number"
                                min={0}
                                max={100}
                                value={industry}
                                disabled={!editable}
                                onChange={(e) =>
                                    setFromIndustry(
                                        Number.parseInt(e.target.value, 10),
                                    )
                                }
                            />
                        </Field>
                        <Field id="teacher" label="Bobot Guru (%)">
                            <Input
                                id="teacher"
                                type="number"
                                value={teacher}
                                disabled
                                readOnly
                            />
                        </Field>
                    </div>

                    {error ? (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    ) : null}

                    {editable ? (
                        <div className="flex justify-end">
                            <Button onClick={save} disabled={saving}>
                                {saving ? (
                                    <LoaderCircle
                                        className="size-4 animate-spin"
                                        aria-hidden="true"
                                    />
                                ) : null}
                                Simpan
                            </Button>
                        </div>
                    ) : (
                        <p className="text-xs text-muted-foreground">
                            Hanya Super Admin yang dapat mengubah bobot.
                        </p>
                    )}
                </CardContent>
            </Card>
        </AppLayout>
    );
}
