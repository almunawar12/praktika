import { Badge } from '@/components/ui/badge';

export type Grade = 'A' | 'B' | 'C' | 'D' | 'E';

// The letter carries the meaning; colour only reinforces it.
const STYLES: Record<Grade, string> = {
    A: 'border-transparent bg-emerald-600/15 text-emerald-700 dark:text-emerald-400',
    B: 'border-transparent bg-blue-600/15 text-blue-700 dark:text-blue-400',
    C: 'border-transparent bg-amber-600/15 text-amber-700 dark:text-amber-400',
    D: 'border-transparent bg-orange-600/15 text-orange-700 dark:text-orange-400',
    E: 'border-transparent bg-destructive/15 text-destructive',
};

export function GradeBadge({ grade }: { grade: Grade }) {
    return (
        <Badge
            className={`${STYLES[grade]} font-semibold`}
            aria-label={`Grade ${grade}`}
        >
            {grade}
        </Badge>
    );
}
