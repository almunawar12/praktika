import { useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { ConfirmDialog } from '@/components/shared/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type FieldsProps<T> = {
    data: T;
    setData: (key: keyof T, value: T[keyof T]) => void;
    errors: Partial<Record<keyof T, string>>;
};

/** Flat, form-convertible shape Inertia's useForm accepts. */
type FormShape = Record<string, string | number | boolean | null | undefined>;

type FormModalProps<T extends Record<string, unknown>> = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    action: string;
    method: 'post' | 'put';
    initialData: T;
    submitLabel: string;
    requireConfirm?: boolean;
    confirmDescription?: string;
    children: (fields: FieldsProps<T>) => ReactNode;
};

export function FormModal<T extends Record<string, unknown>>({
    open,
    onOpenChange,
    title,
    description,
    action,
    method,
    initialData,
    submitLabel,
    requireConfirm = true,
    confirmDescription = 'Pastikan data sudah benar sebelum disimpan.',
    children,
}: FormModalProps<T>) {
    const form = useForm<FormShape>(initialData as FormShape);
    const [confirming, setConfirming] = useState(false);

    const submit = () => {
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                setConfirming(false);
                onOpenChange(false);
                form.reset();
            },
            onError: () => setConfirming(false),
        };

        if (method === 'post') {
            form.post(action, options);
        } else {
            form.put(action, options);
        }
    };

    const handleSave = () => {
        if (requireConfirm) {
            setConfirming(true);
        } else {
            submit();
        }
    };

    return (
        <>
            <Dialog
                open={open}
                onOpenChange={(next) => {
                    if (!next) {
                        form.reset();
                    }

                    onOpenChange(next);
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        {description ? (
                            <DialogDescription>{description}</DialogDescription>
                        ) : null}
                    </DialogHeader>

                    <form
                        id="form-modal"
                        className="grid gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            handleSave();
                        }}
                    >
                        {children({
                            data: form.data as T,
                            setData: (key, value) =>
                                (
                                    form.setData as (
                                        k: string,
                                        v: unknown,
                                    ) => void
                                )(key as string, value),
                            errors: form.errors as Partial<
                                Record<keyof T, string>
                            >,
                        })}
                    </form>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                            disabled={form.processing}
                        >
                            Batal
                        </Button>
                        <Button
                            type="submit"
                            form="form-modal"
                            disabled={form.processing}
                        >
                            {form.processing ? (
                                <LoaderCircle
                                    className="size-4 animate-spin"
                                    aria-hidden="true"
                                />
                            ) : null}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title="Simpan data ini?"
                description={confirmDescription}
                confirmLabel={form.processing ? 'Menyimpan...' : 'Simpan'}
                processing={form.processing}
                onConfirm={submit}
            />
        </>
    );
}
