import { Camera, CircleAlert, RotateCcw } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';

type Props = {
    onCapture: (file: File | null) => void;
    error?: string;
};

/**
 * Live-camera photo input for attendance.
 *
 * Uses getUserMedia and captures a frame from the stream — there is no file input,
 * so a photo can only be taken here and now, never picked from the gallery. Needs a
 * secure context (HTTPS or localhost) and camera permission; both failures surface
 * as an inline message.
 */
export function PhotoCapture({ onCapture, error }: Props) {
    const videoRef = useRef<HTMLVideoElement>(null);
    const streamRef = useRef<MediaStream | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const cameraSupported = !!navigator.mediaDevices?.getUserMedia;
    const [cameraError, setCameraError] = useState<string | null>(() =>
        cameraSupported
            ? null
            : 'Kamera tidak tersedia di perangkat atau browser ini.',
    );

    const stopStream = () => {
        streamRef.current?.getTracks().forEach((track) => track.stop());
        streamRef.current = null;
    };

    // Runs the camera whenever there is no captured photo showing. Retaking clears
    // the preview, which re-runs this and restarts the stream.
    useEffect(() => {
        if (preview !== null || !cameraSupported) {
            return;
        }

        let cancelled = false;

        navigator.mediaDevices
            .getUserMedia({
                video: { facingMode: 'user' },
                audio: false,
            })
            .then((stream) => {
                if (cancelled) {
                    stream.getTracks().forEach((track) => track.stop());

                    return;
                }

                streamRef.current = stream;
                setCameraError(null);

                if (videoRef.current) {
                    videoRef.current.srcObject = stream;
                }
            })
            .catch(() => {
                if (!cancelled) {
                    setCameraError(
                        'Tidak dapat mengakses kamera. Izinkan akses kamera lalu coba lagi.',
                    );
                }
            });

        return () => {
            cancelled = true;
            stopStream();
        };
    }, [preview, cameraSupported]);

    // Release the camera when the component goes away.
    useEffect(() => stopStream, []);

    const capture = () => {
        const video = videoRef.current;

        if (video === null || video.videoWidth === 0) {
            return;
        }

        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        const context = canvas.getContext('2d');

        if (context === null) {
            return;
        }

        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        canvas.toBlob(
            (blob) => {
                if (blob === null) {
                    return;
                }

                const file = new File([blob], 'absensi.jpg', {
                    type: 'image/jpeg',
                });

                onCapture(file);
                setPreview(URL.createObjectURL(blob));
                stopStream();
            },
            'image/jpeg',
            0.9,
        );
    };

    const retake = () => {
        if (preview) {
            URL.revokeObjectURL(preview);
        }

        setPreview(null);
        onCapture(null);
    };

    if (cameraError !== null) {
        return (
            <div className="grid gap-2">
                <div className="flex flex-col items-center gap-2 rounded-lg border border-dashed p-6 text-center">
                    <CircleAlert
                        className="size-6 text-destructive"
                        aria-hidden="true"
                    />
                    <p className="text-sm text-destructive">{cameraError}</p>
                </div>
                {error ? (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                ) : null}
            </div>
        );
    }

    return (
        <div className="grid gap-2">
            {preview ? (
                <div className="grid gap-2">
                    <img
                        src={preview}
                        alt="Pratinjau foto absensi"
                        className="aspect-[4/3] w-full rounded-lg border object-cover"
                    />
                    <Button type="button" variant="outline" onClick={retake}>
                        <RotateCcw className="size-4" aria-hidden="true" />
                        Ambil Ulang
                    </Button>
                </div>
            ) : (
                <div className="grid gap-2">
                    <video
                        ref={videoRef}
                        autoPlay
                        playsInline
                        muted
                        className="aspect-[4/3] w-full rounded-lg border bg-muted object-cover"
                        aria-label="Pratinjau kamera"
                    />
                    <Button
                        type="button"
                        onClick={capture}
                        aria-invalid={Boolean(error)}
                    >
                        <Camera className="size-4" aria-hidden="true" />
                        Ambil Foto
                    </Button>
                    <span className="text-center text-xs text-muted-foreground">
                        Foto diambil langsung dari kamera sebagai bukti
                        kehadiran.
                    </span>
                </div>
            )}

            {error ? (
                <p role="alert" className="text-sm text-destructive">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
