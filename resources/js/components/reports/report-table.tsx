import type { ReactNode } from 'react';
import type { Column } from '@/components/data-table/data-table';
import { DataTableCard } from '@/components/data-table/data-table-card';
import type { Paginated, TableState } from '@/types/table';

export type ReportRow = {
    key: string;
    cells: (string | number)[];
};

type ReportTableProps = {
    columns: string[];
    rows: Paginated<ReportRow>;
    table: TableState;
    search: string;
    onSearchChange: (value: string) => void;
    onPageChange: (page: number) => void;
    onPerPageChange: (value: string) => void;
    filters: ReactNode;
};

/**
 * One recap, rendered from the column headings the server sent. Nothing here
 * knows which report it is showing — the shape comes entirely from props.
 */
export function ReportTable({
    columns,
    rows,
    table,
    search,
    onSearchChange,
    onPageChange,
    onPerPageChange,
    filters,
}: ReportTableProps) {
    const tableColumns: Column<ReportRow>[] = columns.map((header, index) => ({
        key: String(index),
        header,
        // The first three columns identify the student; the rest are figures that
        // may fold away on a phone.
        hideOnMobile: index > 2,
        cell: (row) => row.cells[index],
    }));

    return (
        <DataTableCard
            columns={tableColumns}
            page={rows}
            state={table}
            onSort={() => undefined}
            onPageChange={onPageChange}
            onPerPageChange={onPerPageChange}
            rowKey={(row) => row.key}
            search={search}
            onSearchChange={onSearchChange}
            searchPlaceholder="Cari nama atau NIS"
            filters={filters}
            emptyMessage="Tidak ada data untuk filter ini."
        />
    );
}
