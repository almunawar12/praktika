import { Badge } from '@/components/ui/badge';

export type ReviewStatus = 'pending' | 'approved' | 'rejected';

const LABELS: Record<ReviewStatus, string> = {
    pending: 'Menunggu Review',
    approved: 'Disetujui',
    rejected: 'Ditolak',
};

// Every status keeps a distinct label, so colour is never the only signal.
const STYLES: Record<ReviewStatus, string> = {
    pending: 'border-transparent bg-muted text-muted-foreground',
    approved:
        'border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400',
    rejected: 'border-transparent bg-destructive/15 text-destructive',
};

export function ReviewBadge({
    status,
    prefix,
}: {
    status: ReviewStatus;
    prefix?: string;
}) {
    return (
        <Badge className={STYLES[status]}>
            {prefix ? `${prefix}: ` : ''}
            {LABELS[status]}
        </Badge>
    );
}
