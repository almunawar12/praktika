import { Form, router } from '@inertiajs/react';
import { Ban, CheckCircle2, PlayCircle } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type PlacementAbilities = {
    update: boolean;
    delete: boolean;
    activate: boolean;
    complete: boolean;
    cancel: boolean;
};

type Props = {
    placementId: string;
    can: PlacementAbilities;
};

/**
 * The buttons offered depend on the placement's current status as well as the
 * user's permissions, so `can` is resolved by the server — the shared permission
 * list alone can not tell whether a completed placement may be completed again.
 */
export function PlacementActions({ placementId, can }: Props) {
    const [cancelling, setCancelling] = useState(false);
    const [busy, setBusy] = useState(false);

    const transition = (action: 'activate' | 'complete') => {
        setBusy(true);
        router.put(
            `/pkl/placements/${placementId}/${action}`,
            {},
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };

    if (!can.activate && !can.complete && !can.cancel) {
        return null;
    }

    return (
        <>
            {can.activate ? (
                <Button disabled={busy} onClick={() => transition('activate')}>
                    <PlayCircle className="size-4" aria-hidden="true" />
                    Aktifkan
                </Button>
            ) : null}

            {can.complete ? (
                <Button
                    variant="secondary"
                    disabled={busy}
                    onClick={() => transition('complete')}
                >
                    <CheckCircle2 className="size-4" aria-hidden="true" />
                    Selesaikan
                </Button>
            ) : null}

            {can.cancel ? (
                <Button
                    variant="outline"
                    disabled={busy}
                    onClick={() => setCancelling(true)}
                >
                    <Ban className="size-4" aria-hidden="true" />
                    Batalkan
                </Button>
            ) : null}

            <Dialog open={cancelling} onOpenChange={setCancelling}>
                <DialogContent>
                    <Form
                        action={`/pkl/placements/${placementId}/cancel`}
                        method="put"
                        onSuccess={() => setCancelling(false)}
                    >
                        {({ processing, errors }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>
                                        Batalkan penempatan?
                                    </DialogTitle>
                                    <DialogDescription>
                                        Penempatan yang dibatalkan tetap
                                        tersimpan beserta riwayatnya, dan slot
                                        kuota perusahaan langsung dibebaskan.
                                        Status ini tidak dapat dikembalikan.
                                    </DialogDescription>
                                </DialogHeader>

                                <div className="grid gap-2 py-4">
                                    <Label htmlFor="cancel_reason">
                                        Alasan pembatalan
                                        <span
                                            className="text-destructive"
                                            aria-hidden="true"
                                        >
                                            *
                                        </span>
                                    </Label>
                                    <Textarea
                                        id="cancel_reason"
                                        name="cancel_reason"
                                        rows={3}
                                        required
                                        maxLength={1000}
                                        autoFocus
                                        aria-invalid={Boolean(
                                            errors.cancel_reason,
                                        )}
                                    />
                                    {errors.cancel_reason ? (
                                        <p
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {errors.cancel_reason}
                                        </p>
                                    ) : null}
                                </div>

                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                        onClick={() => setCancelling(false)}
                                    >
                                        Kembali
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="destructive"
                                        disabled={processing}
                                    >
                                        {processing
                                            ? 'Membatalkan...'
                                            : 'Batalkan Penempatan'}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                </DialogContent>
            </Dialog>
        </>
    );
}
