import { router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

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
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    <AlertDialogDescription>
                        {description}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>
                        Batal
                    </AlertDialogCancel>
                    <AlertDialogAction
                        disabled={processing}
                        className="bg-destructive text-white hover:bg-destructive/90"
                        onClick={(event) => {
                            // The dialog must stay open while the request is in flight,
                            // otherwise a failure would close it with no feedback.
                            event.preventDefault();
                            setProcessing(true);

                            router.delete(action, {
                                preserveScroll: true,
                                onFinish: () => {
                                    setProcessing(false);
                                    onOpenChange(false);
                                },
                            });
                        }}
                    >
                        {processing ? 'Menghapus...' : 'Hapus'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
