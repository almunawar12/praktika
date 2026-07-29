import { Search } from 'lucide-react';
import type { ReactNode } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { TablePager } from '@/components/data-table/table-pager';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Paginated, TableState } from '@/types/table';

const PER_PAGE_OPTIONS = ['10', '15', '25', '50'];

type DataTableCardProps<T> = {
    columns: Column<T>[];
    page: Paginated<T>;
    state: TableState;
    onSort: (column: string) => void;
    onPageChange: (page: number) => void;
    onPerPageChange: (value: string) => void;
    rowKey: (row: T) => string;
    search: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder: string;
    /** Filter controls shown to the left of the search box. */
    filters?: ReactNode;
    emptyMessage?: string;
};

/**
 * The standard list surface: a card holding a per-page selector, filters and
 * search, the table, and a numbered pager — so every module's list reads the same.
 */
export function DataTableCard<T>({
    columns,
    page,
    state,
    onSort,
    onPageChange,
    onPerPageChange,
    rowKey,
    search,
    onSearchChange,
    searchPlaceholder,
    filters,
    emptyMessage,
}: DataTableCardProps<T>) {
    return (
        <Card>
            <CardContent className="flex flex-col gap-4">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Select
                            value={String(page.per_page)}
                            onValueChange={onPerPageChange}
                        >
                            <SelectTrigger
                                className="w-[72px]"
                                aria-label="Jumlah baris per halaman"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {PER_PAGE_OPTIONS.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        data per halaman
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        {filters}
                        <div className="relative sm:w-64">
                            <Search
                                className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    onSearchChange(event.target.value)
                                }
                                placeholder={searchPlaceholder}
                                className="pl-9"
                                aria-label={searchPlaceholder}
                            />
                        </div>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    page={page}
                    state={state}
                    onSort={onSort}
                    rowKey={rowKey}
                    emptyMessage={emptyMessage}
                />

                <TablePager page={page} onPageChange={onPageChange} />
            </CardContent>
        </Card>
    );
}
