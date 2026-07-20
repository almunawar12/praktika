import { CalendarClock, CalendarDays, ScrollText, Users } from 'lucide-react';
import type { Column } from '@/components/data-table/data-table';
import { DataTableCard } from '@/components/data-table/data-table-card';
import { FilterSelect } from '@/components/data-table/filter-select';
import { StatCard } from '@/components/shared/stat-card';
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

type Stats = {
    total: number;
    today: number;
    week: number;
    contributors: number;
};

type Props = {
    activities: Paginated<ActivityRow>;
    table: TableState;
    filterOptions: {
        logNames: string[];
        events: string[];
    };
    stats: Stats;
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
    stats,
}: Props) {
    const { search, setSearch, sortBy, filterBy, goToPage, setPerPage } =
        useTableQuery('/system/activity', table);

    const columns: Column<ActivityRow>[] = [
        {
            key: 'no',
            header: 'No',
            className: 'w-12 text-muted-foreground',
            cell: (_row, index) => (
                <span className="tabular-nums">
                    {activities.from !== null
                        ? activities.from + index
                        : index + 1}
                </span>
            ),
        },
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
        <AppLayout
            title="Log Aktivitas"
            description="Jejak audit sistem — siapa mengubah apa dan kapan."
        >
            <div className="flex flex-col gap-6">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        icon={ScrollText}
                        tone="blue"
                        label="Total Aktivitas"
                        value={stats.total}
                        hint="Seluruh jejak"
                    />
                    <StatCard
                        icon={CalendarClock}
                        tone="green"
                        label="Hari Ini"
                        value={stats.today}
                        hint="Aktivitas hari ini"
                    />
                    <StatCard
                        icon={CalendarDays}
                        tone="amber"
                        label="7 Hari Terakhir"
                        value={stats.week}
                        hint="Sepekan terakhir"
                    />
                    <StatCard
                        icon={Users}
                        tone="violet"
                        label="Kontributor"
                        value={stats.contributors}
                        hint="Pengguna berbeda"
                    />
                </div>

                <DataTableCard
                    columns={columns}
                    page={activities}
                    state={table}
                    onSort={sortBy}
                    onPageChange={goToPage}
                    onPerPageChange={setPerPage}
                    rowKey={(row) => row.id}
                    search={search}
                    onSearchChange={setSearch}
                    searchPlaceholder="Cari deskripsi..."
                    emptyMessage="Belum ada aktivitas tercatat."
                    filters={
                        <>
                            <FilterSelect
                                label="Filter kategori"
                                allLabel="Semua kategori"
                                value={table.filters.log_name ?? 'all'}
                                onValueChange={(value) =>
                                    filterBy('log_name', value)
                                }
                                options={filterOptions.logNames.map((name) => ({
                                    value: name,
                                    label: name,
                                }))}
                            />
                            <FilterSelect
                                label="Filter peristiwa"
                                allLabel="Semua peristiwa"
                                value={table.filters.event ?? 'all'}
                                onValueChange={(value) =>
                                    filterBy('event', value)
                                }
                                options={filterOptions.events.map((event) => ({
                                    value: event,
                                    label: event,
                                }))}
                            />
                        </>
                    }
                />
            </div>
        </AppLayout>
    );
}
