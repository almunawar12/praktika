import { NavIcon } from '@/components/layout/nav-icon';
import { Card, CardContent } from '@/components/ui/card';

export type Stat = {
    label: string;
    icon: string;
    value: number | null;
    pending: boolean;
};

export function StatCard({ stat }: { stat: Stat }) {
    return (
        <Card>
            <CardContent className="flex items-center gap-4">
                <span className="flex size-10 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                    <NavIcon name={stat.icon} className="size-5" />
                </span>

                <div className="min-w-0">
                    <p className="truncate text-sm text-muted-foreground">
                        {stat.label}
                    </p>

                    {stat.pending ? (
                        // Deliberately not a number: this metric has no data source until
                        // the relevant PKL module ships, and a fake zero would read as fact.
                        <p className="text-sm font-medium text-muted-foreground/70">
                            Menunggu modul PKL
                        </p>
                    ) : (
                        <p className="text-2xl font-semibold tabular-nums">
                            {stat.value}
                        </p>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
