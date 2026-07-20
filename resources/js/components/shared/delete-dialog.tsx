import { router } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';

type DeleteDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Route to DELETE. */
    action: string;
    title?: string;
    description: string;
};

export function DeleteDialog({
    open,
    onOpenChange,
    action,
    title = 'Hapus data ini?',
    description,
}: DeleteDialogProps) {
    const [processing, setProcessing] = useState(false);

    return (
        <ConfirmDialog
            open={open}
            onOpenChange={onOpenChange}
            title={title}
            description={description}
            confirmLabel={processing ? 'Menghapus...' : 'Hapus'}
            variant="destructive"
            processing={processing}
            onConfirm={() => {
                // The dialog must stay open while the request is in flight,
                // otherwise a failure would close it with no feedback.
                setProcessing(true);
                router.delete(action, {
                    preserveScroll: true,
                    onFinish: () => {
                        setProcessing(false);
                        onOpenChange(false);
                    },
                });
            }}
        />
    );
}
