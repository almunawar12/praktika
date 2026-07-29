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

export type StudentAttendedNeedingVisit = {
    placementId: string;
    student: string;
    company: string;
};

export type TeacherOverview = {
    studentsNeedingAttention: {
        placementId: string;
        student: string;
        company: string;
        alphaCount: number;
        journalPercentage: number;
    }[];
    upcomingVisits: StudentAttendedNeedingVisit[];
};

export type StudentOverview = {
    placement: {
        company: string;
        teacher: string;
        startDate: string;
        endDate: string;
    };
    todayStatus: {
        status: 'present' | 'permit' | 'sick' | 'absent' | null;
        checkInAt: string | null;
        checkOutAt: string | null;
    };
    progress: {
        daysElapsed: number;
        totalDays: number;
        percentage: number;
    };
    attendanceRecap: {
        hadir: number;
        izin: number;
        sakit: number;
        alpha: number;
    };
    journalToday: {
        filled: boolean;
    };
    journalCompletion: {
        filled: number;
        workdaysElapsed: number;
        percentage: number;
    };
    journalStatus: {
        pending: number;
        approved: number;
        rejected: number;
    };
    rejectedJournals: {
        id: string;
        date: string;
        note: string | null;
    }[];
    finalScore: {
        score: number;
        grade: string;
        publishedAt: string | null;
    } | null;
};
