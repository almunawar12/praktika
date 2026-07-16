import { ActivityChart } from '@/components/dashboard/activity-chart';
import { AttendanceDonut } from '@/components/dashboard/attendance-donut';
import { OverviewStatCard } from '@/components/dashboard/overview-stat-card';
import type { DashboardOverview } from '@/types/dashboard';

export function AdminOverview({ overview }: { overview: DashboardOverview }) {
    return (
        <div className="flex flex-col gap-6">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                {overview.stats.map((stat) => (
                    <OverviewStatCard key={stat.label} stat={stat} />
                ))}
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <div className="xl:col-span-2">
                    <ActivityChart data={overview.activityChart} />
                </div>
                <AttendanceDonut data={overview.attendanceToday} />
            </div>
        </div>
    );
}
