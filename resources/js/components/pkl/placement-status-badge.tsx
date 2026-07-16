import { Badge } from '@/components/ui/badge';

export type PlacementStatus = 'draft' | 'active' | 'completed' | 'cancelled';

const LABELS: Record<PlacementStatus, string> = {
    draft: 'Draft',
    active: 'Aktif',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
};

// Colour carries meaning here, so each status also keeps a distinct label — the
// badge never relies on hue alone to be readable.
const STYLES: Record<PlacementStatus, string> = {
    draft: 'border-transparent bg-muted text-muted-foreground',
    active: 'border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400',
    completed:
        'border-transparent bg-blue-600/15 text-blue-700 dark:text-blue-400',
    cancelled: 'border-transparent bg-destructive/15 text-destructive',
};

export function PlacementStatusBadge({ status }: { status: PlacementStatus }) {
    return <Badge className={STYLES[status]}>{LABELS[status]}</Badge>;
}
