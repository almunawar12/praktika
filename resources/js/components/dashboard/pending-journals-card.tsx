import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatRelativeTime } from '@/lib/format';
import type { PendingJournal } from '@/types/dashboard';

function initials(name: string): string {
    return name
        .split(' ')
        .slice(0, 2)
        .map((word) => word.charAt(0))
        .join('')
        .toUpperCase();
}

export function PendingJournalsCard({ items }: { items: PendingJournal[] }) {
    return (
        <Card className="flex flex-col">
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Jurnal Pending</CardTitle>
                <Button variant="outline" size="sm" asChild>
                    <Link href="/journals">Lihat Semua</Link>
                </Button>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-4">
                {items.length === 0 ? (
                    <p className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                        Tidak ada jurnal pending.
                    </p>
                ) : (
                    items.map((journal) => (
                        <div
                            key={journal.id}
                            className="flex items-start gap-3"
                        >
                            <Avatar className="size-9">
                                <AvatarFallback className="text-xs">
                                    {initials(journal.student)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-medium">
                                    {journal.student}
                                </p>
                                <p className="truncate text-xs text-muted-foreground">
                                    {journal.className} · {journal.company}
                                </p>
                            </div>
                            <span className="shrink-0 text-xs text-muted-foreground">
                                {formatRelativeTime(journal.submittedAt)}
                            </span>
                        </div>
                    ))
                )}
            </CardContent>
            {items.length > 0 ? (
                <CardFooter>
                    <Link
                        href="/journals"
                        className="flex items-center gap-1 text-sm text-primary hover:underline"
                    >
                        Lihat semua jurnal pending
                        <ArrowRight className="size-4" aria-hidden="true" />
                    </Link>
                </CardFooter>
            ) : null}
        </Card>
    );
}
