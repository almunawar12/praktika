import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { DataTableToolbar } from '@/components/data-table/data-table-toolbar';
import { Badge } from '@/components/ui/badge';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, TableState } from '@/types/table';

type ActivityRow = {
    id: string;
    log_name: string | null;
    description: string;
    event: string | null;
    causer: string | null;
    subject_type: string | null;
    created_at: string | null;
};

type Props = {
    activities: Paginated<ActivityRow>;
    table: TableState;
    filterOptions: {
        logNames: string[];
        events: string[];
    };
};

function formatDate(value: string | null): string {
    return value === null
        ? '—'
        : new Date(value).toLocaleString('id-ID', {
              dateStyle: 'medium',
              timeStyle: 'short',
          });
}

export default function ActivityIndex({
    activities,
    table,
    filterOptions,
}: Props) {
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/system/activity', table);

    const columns: Column<ActivityRow>[] = [
        {
            key: 'description',
            header: 'Aktivitas',
            cell: (row) => (
                <div className="grid gap-0.5">
                    <span className="font-medium">{row.description}</span>
                    <span className="text-xs text-muted-foreground">
                        {row.log_name ?? 'umum'}
                        {row.subject_type ? ` · ${row.subject_type}` : ''}
                    </span>
                </div>
            ),
        },
        {
            key: 'event',
            header: 'Peristiwa',
            hideOnMobile: true,
            cell: (row) =>
                row.event ? (
                    <Badge variant="secondary">{row.event}</Badge>
                ) : (
                    <span className="text-muted-foreground">—</span>
                ),
        },
        {
            key: 'causer',
            header: 'Oleh',
            cell: (row) => (
                <span className="text-sm">
                    {row.causer ?? (
                        <span className="text-muted-foreground">Sistem</span>
                    )}
                </span>
            ),
        },
        {
            key: 'created_at',
            header: 'Waktu',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-sm text-muted-foreground">
                    {formatDate(row.created_at)}
                </span>
            ),
        },
    ];

    return (
        <AppLayout title="Log Aktivitas" description="Jejak audit sistem">
            <div className="flex flex-col gap-4">
                <DataTableToolbar
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari deskripsi..."
                    filters={[
                        {
                            key: 'log_name',
                            label: 'Kategori',
                            value: table.filters.log_name ?? 'all',
                            options: filterOptions.logNames.map((name) => ({
                                value: name,
                                label: name,
                            })),
                        },
                        {
                            key: 'event',
                            label: 'Peristiwa',
                            value: table.filters.event ?? 'all',
                            options: filterOptions.events.map((event) => ({
                                value: event,
                                label: event,
                            })),
                        },
                    ]}
                    onFilterChange={filterBy}
                />

                <DataTable
                    columns={columns}
                    page={activities}
                    state={table}
                    onSort={sortBy}
                    rowKey={(row) => row.id}
                    emptyMessage="Belum ada aktivitas tercatat."
                />

                <DataTablePagination
                    page={activities}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                />
            </div>
        </AppLayout>
    );
}
