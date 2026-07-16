import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Paginated } from '@/types/table';

type PaginationProps<T> = {
    page: Paginated<T>;
    onPageChange: (page: number) => void;
    onPerPageChange: (value: string) => void;
};

const PER_PAGE_OPTIONS = ['15', '25', '50', '100'];

export function DataTablePagination<T>({
    page,
    onPageChange,
    onPerPageChange,
}: PaginationProps<T>) {
    return (
        <div className="flex flex-col-reverse items-center gap-3 sm:flex-row sm:justify-between">
            <p className="text-sm text-muted-foreground">
                {page.total === 0
                    ? 'Tidak ada data'
                    : `Menampilkan ${page.from}–${page.to} dari ${page.total} data`}
            </p>

            <div className="flex items-center gap-2">
                <Select
                    value={String(page.per_page)}
                    onValueChange={onPerPageChange}
                >
                    <SelectTrigger
                        className="w-[88px]"
                        aria-label="Jumlah baris per halaman"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {PER_PAGE_OPTIONS.map((option) => (
                            <SelectItem key={option} value={option}>
                                {option} / hal
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>

                <Button
                    variant="outline"
                    size="icon"
                    disabled={page.current_page <= 1}
                    onClick={() => onPageChange(page.current_page - 1)}
                >
                    <ChevronLeft className="size-4" aria-hidden="true" />
                    <span className="sr-only">Halaman sebelumnya</span>
                </Button>

                <span className="min-w-[5rem] text-center text-sm tabular-nums">
                    Hal {page.current_page} / {page.last_page}
                </span>

                <Button
                    variant="outline"
                    size="icon"
                    disabled={page.current_page >= page.last_page}
                    onClick={() => onPageChange(page.current_page + 1)}
                >
                    <ChevronRight className="size-4" aria-hidden="true" />
                    <span className="sr-only">Halaman berikutnya</span>
                </Button>
            </div>
        </div>
    );
}
