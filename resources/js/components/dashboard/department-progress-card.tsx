import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { DepartmentShare } from '@/types/dashboard';

export function DepartmentProgressCard({
    items,
}: {
    items: DepartmentShare[];
}) {
    const total = items.reduce((sum, item) => sum + item.count, 0);

    return (
        <Card className="flex flex-col">
            <CardHeader>
                <CardTitle>PKL Aktif Berdasarkan Jurusan</CardTitle>
            </CardHeader>
            <CardContent className="flex flex-1 flex-col gap-4">
                {items.length === 0 ? (
                    <p className="flex flex-1 items-center justify-center text-sm text-muted-foreground">
                        Belum ada PKL aktif.
                    </p>
                ) : (
                    items.map((item) => (
                        <div key={item.name} className="flex flex-col gap-1.5">
                            <div className="flex items-baseline justify-between gap-2 text-sm">
                                <span className="truncate">{item.name}</span>
                                <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                                    {item.count} ({item.percentage}%)
                                </span>
                            </div>
                            <div
                                role="progressbar"
                                aria-valuenow={item.percentage}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-label={item.name}
                                className="h-2 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    className="h-full rounded-full bg-(--chart-1)"
                                    style={{ width: `${item.percentage}%` }}
                                />
                            </div>
                        </div>
                    ))
                )}
            </CardContent>
            {items.length > 0 ? (
                <CardFooter className="justify-between border-t text-sm">
                    <span className="text-muted-foreground">
                        Total Siswa Aktif
                    </span>
                    <span className="font-medium tabular-nums">
                        {total} Siswa
                    </span>
                </CardFooter>
            ) : null}
        </Card>
    );
}
