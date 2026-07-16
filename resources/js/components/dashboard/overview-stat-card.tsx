import { NavIcon } from '@/components/layout/nav-icon';
import { Card, CardContent } from '@/components/ui/card';
import type { OverviewStat } from '@/types/dashboard';

/**
 * Decorative icon tint per tile, keyed by the icon the server names so the
 * palette needs no server round-trip. Fallback keeps unknown icons readable.
 */
const tones: Record<string, string> = {
    GraduationCap: 'bg-violet-500/10 text-violet-600 dark:text-violet-400',
    Building2: 'bg-orange-500/10 text-orange-600 dark:text-orange-400',
    MapPin: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
    Award: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    NotebookPen: 'bg-rose-500/10 text-rose-600 dark:text-rose-400',
    CalendarCheck: 'bg-sky-500/10 text-sky-600 dark:text-sky-400',
};

const subStyles: Record<OverviewStat['subVariant'], string> = {
    positive: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-red-600 dark:text-red-400',
    muted: 'text-muted-foreground',
};

export function OverviewStatCard({ stat }: { stat: OverviewStat }) {
    return (
        <Card>
            <CardContent className="flex flex-col gap-3">
                <div className="flex items-center gap-3">
                    <span
                        className={`flex size-10 shrink-0 items-center justify-center rounded-lg ${tones[stat.icon] ?? 'bg-muted text-muted-foreground'}`}
                    >
                        <NavIcon name={stat.icon} className="size-5" />
                    </span>
                    <p className="truncate text-sm text-muted-foreground">
                        {stat.label}
                    </p>
                </div>

                <p className="text-2xl font-semibold tabular-nums">
                    {stat.value}
                </p>

                <p className={`text-xs ${subStyles[stat.subVariant]}`}>
                    {stat.subLabel}
                </p>
            </CardContent>
        </Card>
    );
}
