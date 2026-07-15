import { StatCard } from '@/components/dashboard/stat-card';
import type { Stat } from '@/components/dashboard/stat-card';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useAuth } from '@/hooks/use-auth';
import AppLayout from '@/layouts/app-layout';

type RecentActivity = {
    id: string;
    description: string;
    event: string | null;
    causer: string | null;
    createdAt: string | null;
};

type DashboardProps = {
    role: string | null;
    roleLabel: string | null;
    stats: Stat[];
    recentActivities: RecentActivity[];
};

function formatDate(iso: string | null): string {
    if (iso === null) {
        return '-';
    }

    return new Intl.DateTimeFormat('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(iso));
}

export default function Dashboard({
    roleLabel,
    stats,
    recentActivities,
}: DashboardProps) {
    const { user } = useAuth();

    return (
        <AppLayout
            title="Dashboard"
            description={`Selamat datang, ${user?.name ?? ''}`}
        >
            <div className="flex flex-col gap-6">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-sm text-muted-foreground">
                        Masuk sebagai
                    </span>
                    <Badge variant="secondary">{roleLabel}</Badge>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {stats.map((stat) => (
                        <StatCard key={stat.label} stat={stat} />
                    ))}
                </div>

                {recentActivities.length > 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Aktivitas Terbaru</CardTitle>
                            <CardDescription>
                                10 catatan audit terakhir
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            {recentActivities.map((activity) => (
                                <div
                                    key={activity.id}
                                    className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 border-b pb-3 last:border-0 last:pb-0"
                                >
                                    <div className="min-w-0">
                                        <p className="text-sm font-medium">
                                            {activity.description}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {activity.causer ?? 'Sistem'}
                                        </p>
                                    </div>
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {formatDate(activity.createdAt)}
                                    </span>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ) : null}
            </div>
        </AppLayout>
    );
}
