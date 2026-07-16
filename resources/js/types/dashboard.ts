export type OverviewStat = {
    label: string;
    icon: string;
    value: number;
    subLabel: string;
    subVariant: 'positive' | 'warning' | 'muted';
};

export type ActivityChartPoint = {
    date: string;
    absensi: number;
    jurnal: number;
    monitoring: number;
    penilaian: number;
};

export type AttendanceToday = {
    hadir: number;
    izin: number;
    sakit: number;
    alpha: number;
    total: number;
    rate: number;
};

export type PendingJournal = {
    id: string;
    student: string;
    className: string;
    company: string;
    submittedAt: string | null;
};

export type RecentAssessment = {
    id: string;
    student: string;
    company: string;
    type: 'Industri' | 'Guru';
    score: number | null;
};

export type DepartmentShare = {
    name: string;
    count: number;
    percentage: number;
};

export type DashboardOverview = {
    stats: OverviewStat[];
    activityChart: ActivityChartPoint[];
    attendanceToday: AttendanceToday;
    pendingJournals: PendingJournal[];
    recentAssessments: RecentAssessment[];
    activeByDepartment: DepartmentShare[];
};
