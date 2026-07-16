import { ActivityChart } from '@/components/dashboard/activity-chart';
import { AttendanceDonut } from '@/components/dashboard/attendance-donut';
import { DepartmentProgressCard } from '@/components/dashboard/department-progress-card';
import { OverviewStatCard } from '@/components/dashboard/overview-stat-card';
import { PendingJournalsCard } from '@/components/dashboard/pending-journals-card';
import { RecentAssessmentsCard } from '@/components/dashboard/recent-assessments-card';
import type { DashboardOverview } from '@/types/dashboard';

export function AdminOverview({ overview }: { overview: DashboardOverview }) {
    return (
        <div className="flex flex-col gap-6">
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                {overview.stats.map((stat) => (
                    <OverviewStatCard key={stat.label} stat={stat} />
                ))}
            </div>

            {/*
             * `min-w-0` on both cells is load-bearing: a grid item defaults to
             * min-width:auto, which lets the charts' own measured width push the
             * track — and the page — wider than the viewport instead of shrinking.
             */}
            <div className="grid gap-6 xl:grid-cols-3">
                <div className="min-w-0 xl:col-span-2">
                    <ActivityChart data={overview.activityChart} />
                </div>
                <div className="min-w-0">
                    <AttendanceDonut data={overview.attendanceToday} />
                </div>
            </div>

            <div className="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                <PendingJournalsCard items={overview.pendingJournals} />
                <RecentAssessmentsCard items={overview.recentAssessments} />
                <DepartmentProgressCard items={overview.activeByDepartment} />
            </div>
        </div>
    );
}
