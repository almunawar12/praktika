import { ArrowDown, ArrowUp, ChevronsUpDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { Paginated, TableState } from '@/types/table';

export type Column<T> = {
    key: string;
    header: string;
    /** Set when the server allows sorting on this column (see Service::SORTABLE). */
    sortable?: boolean;
    cell: (row: T) => ReactNode;
    /** Hidden below `md` — use for secondary columns so mobile stays readable. */
    hideOnMobile?: boolean;
    className?: string;
};

type DataTableProps<T> = {
    columns: Column<T>[];
    page: Paginated<T>;
    state: TableState;
    onSort: (column: string) => void;
    emptyMessage?: string;
    rowKey: (row: T) => string;
};

function SortIcon({
    active,
    direction,
}: {
    active: boolean;
    direction: 'asc' | 'desc';
}) {
    if (!active) {
        return (
            <ChevronsUpDown
                className="size-3.5 opacity-40"
                aria-hidden="true"
            />
        );
    }

    return direction === 'asc' ? (
        <ArrowUp className="size-3.5" aria-hidden="true" />
    ) : (
        <ArrowDown className="size-3.5" aria-hidden="true" />
    );
}

export function DataTable<T>({
    columns,
    page,
    state,
    onSort,
    emptyMessage = 'Tidak ada data.',
    rowKey,
}: DataTableProps<T>) {
    return (
        // The wrapper scrolls, not the page: a wide table must never make the whole
        // layout scroll sideways on a phone.
        <div className="w-full overflow-x-auto rounded-lg border">
            <Table>
                <TableHeader>
                    <TableRow>
                        {columns.map((column) => {
                            const active = state.sort === column.key;

                            return (
                                <TableHead
                                    key={column.key}
                                    className={cn(
                                        column.hideOnMobile &&
                                            'hidden md:table-cell',
                                        column.className,
                                    )}
                                    aria-sort={
                                        active
                                            ? state.direction === 'asc'
                                                ? 'ascending'
                                                : 'descending'
                                            : undefined
                                    }
                                >
                                    {column.sortable ? (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="-ml-3 h-8 gap-1.5 data-[active=true]:text-foreground"
                                            data-active={active}
                                            onClick={() => onSort(column.key)}
                                        >
                                            {column.header}
                                            <SortIcon
                                                active={active}
                                                direction={state.direction}
                                            />
                                        </Button>
                                    ) : (
                                        column.header
                                    )}
                                </TableHead>
                            );
                        })}
                    </TableRow>
                </TableHeader>

                <TableBody>
                    {page.data.length === 0 ? (
                        <TableRow>
                            <TableCell
                                colSpan={columns.length}
                                className="h-24 text-center text-muted-foreground"
                            >
                                {emptyMessage}
                            </TableCell>
                        </TableRow>
                    ) : (
                        page.data.map((row) => (
                            <TableRow key={rowKey(row)}>
                                {columns.map((column) => (
                                    <TableCell
                                        key={column.key}
                                        className={cn(
                                            column.hideOnMobile &&
                                                'hidden md:table-cell',
                                            column.className,
                                        )}
                                    >
                                        {column.cell(row)}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))
                    )}
                </TableBody>
            </Table>
        </div>
    );
}
