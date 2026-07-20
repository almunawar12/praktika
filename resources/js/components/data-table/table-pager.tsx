import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types/table';

type TablePagerProps<T> = {
    page: Paginated<T>;
    onPageChange: (page: number) => void;
};

/** The compact set of page numbers to show: ends, current, and its neighbours. */
function pageWindow(current: number, last: number): number[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const pages = new Set([1, last, current, current - 1, current + 1]);

    return [...pages]
        .filter((page) => page >= 1 && page <= last)
        .sort((a, b) => a - b);
}

export function TablePager<T>({ page, onPageChange }: TablePagerProps<T>) {
    const pages = pageWindow(page.current_page, page.last_page);

    return (
        <div className="flex flex-col-reverse items-center gap-3 sm:flex-row sm:justify-between">
            <p className="text-sm text-muted-foreground">
                {page.total === 0
                    ? 'Tidak ada data'
                    : `Menampilkan ${page.from} sampai ${page.to} dari ${page.total} data`}
            </p>

            {page.last_page > 1 ? (
                <div className="flex items-center gap-1">
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-9"
                        disabled={page.current_page <= 1}
                        onClick={() => onPageChange(page.current_page - 1)}
                        aria-label="Halaman sebelumnya"
                    >
                        &lsaquo;
                    </Button>

                    {pages.map((value, i) => {
                        const gap = i > 0 && value - pages[i - 1] > 1;

                        return (
                            <div
                                key={value}
                                className="flex items-center gap-1"
                            >
                                {gap ? (
                                    <span className="px-1 text-muted-foreground">
                                        …
                                    </span>
                                ) : null}
                                <Button
                                    variant={
                                        value === page.current_page
                                            ? 'default'
                                            : 'outline'
                                    }
                                    size="icon"
                                    className="size-9 tabular-nums"
                                    onClick={() => onPageChange(value)}
                                    aria-current={
                                        value === page.current_page
                                            ? 'page'
                                            : undefined
                                    }
                                >
                                    {value}
                                </Button>
                            </div>
                        );
                    })}

                    <Button
                        variant="outline"
                        size="icon"
                        className="size-9"
                        disabled={page.current_page >= page.last_page}
                        onClick={() => onPageChange(page.current_page + 1)}
                        aria-label="Halaman berikutnya"
                    >
                        &rsaquo;
                    </Button>
                </div>
            ) : null}
        </div>
    );
}
