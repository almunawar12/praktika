import { Camera, RotateCcw } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    onCapture: (file: File | null) => void;
    error?: string;
};

/**
 * Photo input for attendance.
 *
 * Uses a plain file input with `capture="user"` rather than a getUserMedia preview:
 * it opens the phone's own camera, works without HTTPS quirks or permission
 * plumbing, and still lets a desktop user pick a file. The preview below is just
 * confirmation of what will be sent.
 */
export function PhotoCapture({ onCapture, error }: Props) {
    const inputRef = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<string | null>(null);

    const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;

        if (preview) {
            // Object URLs pin the blob in memory until revoked.
            URL.revokeObjectURL(preview);
        }

        setPreview(file ? URL.createObjectURL(file) : null);
        onCapture(file);
    };

    const reset = () => {
        if (preview) {
            URL.revokeObjectURL(preview);
        }

        setPreview(null);
        onCapture(null);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    return (
        <div className="grid gap-2">
            <input
                ref={inputRef}
                type="file"
                name="photo"
                accept="image/*"
                capture="user"
                className="sr-only"
                onChange={handleChange}
                aria-invalid={Boolean(error)}
            />

            {preview ? (
                <div className="grid gap-2">
                    <img
                        src={preview}
                        alt="Pratinjau foto absensi"
                        className="aspect-[4/3] w-full rounded-lg border object-cover"
                    />
                    <Button type="button" variant="outline" onClick={reset}>
                        <RotateCcw className="size-4" aria-hidden="true" />
                        Ambil Ulang
                    </Button>
                </div>
            ) : (
                <Button
                    type="button"
                    variant="outline"
                    className="h-32 w-full flex-col gap-2 border-dashed"
                    onClick={() => inputRef.current?.click()}
                >
                    <Camera className="size-6" aria-hidden="true" />
                    Ambil Foto
                    <span className="text-xs font-normal text-muted-foreground">
                        Wajib sebagai bukti kehadiran
                    </span>
                </Button>
            )}

            {error ? (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
