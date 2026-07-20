import type { LucideIcon } from 'lucide-react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export type StatTone = 'blue' | 'green' | 'amber' | 'violet';

const TONE: Record<StatTone, string> = {
    blue: 'bg-blue-500/10 text-blue-600 dark:text-blue-400',
    green: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    amber: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    violet: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
};

type StatCardProps = {
    icon: LucideIcon;
    label: string;
    value: number | string;
    hint?: string;
    tone?: StatTone;
};

export function StatCard({
    icon: Icon,
    label,
    value,
    hint,
    tone = 'blue',
}: StatCardProps) {
    return (
        <Card>
            <CardContent className="flex items-center gap-4">
                <span
                    className={cn(
                        'flex size-12 shrink-0 items-center justify-center rounded-xl',
                        TONE[tone],
                    )}
                >
                    <Icon className="size-6" aria-hidden="true" />
                </span>
                <div className="grid gap-0.5">
                    <span className="text-sm text-muted-foreground">
                        {label}
                    </span>
                    <span className="text-2xl font-semibold tabular-nums leading-none">
                        {value}
                    </span>
                    {hint ? (
                        <span className="text-xs text-muted-foreground">
                            {hint}
                        </span>
                    ) : null}
                </div>
            </CardContent>
        </Card>
    );
}
