import { Badge } from '@/components/ui/badge';

export type AttendanceStatus = 'present' | 'permit' | 'sick' | 'absent';
export type VerificationStatus = 'pending' | 'verified' | 'rejected';

const STATUS_LABELS: Record<AttendanceStatus, string> = {
    present: 'Hadir',
    permit: 'Izin',
    sick: 'Sakit',
    absent: 'Alpha',
};

// Every status keeps a distinct label, so colour is never the only signal.
const STATUS_STYLES: Record<AttendanceStatus, string> = {
    present:
        'border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400',
    permit: 'border-transparent bg-amber-600/15 text-amber-700 dark:text-amber-400',
    sick: 'border-transparent bg-blue-600/15 text-blue-700 dark:text-blue-400',
    absent: 'border-transparent bg-destructive/15 text-destructive',
};

const VERIFICATION_LABELS: Record<VerificationStatus, string> = {
    pending: 'Menunggu Verifikasi',
    verified: 'Terverifikasi',
    rejected: 'Ditolak',
};

const VERIFICATION_STYLES: Record<VerificationStatus, string> = {
    pending: 'border-transparent bg-muted text-muted-foreground',
    verified:
        'border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400',
    rejected: 'border-transparent bg-destructive/15 text-destructive',
};

export function AttendanceStatusBadge({
    status,
}: {
    status: AttendanceStatus;
}) {
    return (
        <Badge className={STATUS_STYLES[status]}>{STATUS_LABELS[status]}</Badge>
    );
}

export function VerificationBadge({ status }: { status: VerificationStatus }) {
    return (
        <Badge className={VERIFICATION_STYLES[status]}>
            {VERIFICATION_LABELS[status]}
        </Badge>
    );
}
