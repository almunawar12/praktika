import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = {
    code: string;
    label: string;
    value: number | '';
    onChange: (value: number | '') => void;
    error?: string;
    disabled?: boolean;
};

/**
 * One criterion, 0–100 (PRD § 4).
 *
 * The bar is a read-out of the typed number, not a second control: a slider alone
 * would make an exact 85 fiddly to hit, and assessors think in whole marks.
 */
export function ScoreInput({
    code,
    label,
    value,
    onChange,
    error,
    disabled,
}: Props) {
    const pct =
        typeof value === 'number' ? Math.min(100, Math.max(0, value)) : 0;

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between gap-4">
                <Label htmlFor={`score-${code}`}>{label}</Label>
                <Input
                    id={`score-${code}`}
                    name={`scores[${code}]`}
                    type="number"
                    min={0}
                    max={100}
                    step="1"
                    inputMode="numeric"
                    value={value}
                    disabled={disabled}
                    onChange={(event) => {
                        const raw = event.target.value;
                        onChange(raw === '' ? '' : Number(raw));
                    }}
                    className="w-24 text-right tabular-nums"
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? `score-${code}-error` : undefined}
                />
            </div>

            <div
                className="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                role="presentation"
            >
                <div
                    className={cn(
                        'h-full rounded-full transition-all',
                        pct >= 80
                            ? 'bg-emerald-600'
                            : pct >= 60
                              ? 'bg-amber-500'
                              : 'bg-destructive',
                    )}
                    style={{ width: `${pct}%` }}
                />
            </div>

            {error ? (
                <p
                    id={`score-${code}-error`}
                    role="alert"
                    className="text-sm text-destructive"
                >
                    {error}
                </p>
            ) : null}
        </div>
    );
}
