import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';

type FieldProps = {
    id: string;
    label: string;
    error?: string;
    required?: boolean;
    hint?: string;
    children: ReactNode;
};

/**
 * Label + control + error, wired so the message is announced to screen readers
 * rather than only shown in red.
 */
export function Field({
    id,
    label,
    error,
    required,
    hint,
    children,
}: FieldProps) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>
                {label}
                {required ? (
                    <span className="text-destructive" aria-hidden="true">
                        *
                    </span>
                ) : null}
            </Label>

            {children}

            {hint && !error ? (
                <p className="text-xs text-muted-foreground">{hint}</p>
            ) : null}

            {error ? (
                <p
                    id={`${id}-error`}
                    role="alert"
                    className="text-sm text-destructive"
                >
                    {error}
                </p>
            ) : null}
        </div>
    );
}

/**
 * Read-only label/value pair for detail pages.
 */
export function DetailItem({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-1">
            <dt className="text-sm text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium break-words">{children}</dd>
        </div>
    );
}
