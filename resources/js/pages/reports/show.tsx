import { Download } from 'lucide-react';
import type {
    ReportFilterOptions,
    ReportFilterState,
} from '@/components/reports/report-filter-bar';
import { ReportFilterBar } from '@/components/reports/report-filter-bar';
import type { ReportRow } from '@/components/reports/report-table';
import { ReportTable } from '@/components/reports/report-table';
import type { StudentSummary } from '@/components/reports/student-summary-card';
import { StudentSummaryCard } from '@/components/reports/student-summary-card';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, SelectOption, TableState } from '@/types/table';

type Props = {
    type: { value: string; label: string };
    columns: string[];
    scope: string;
    rows: Paginated<ReportRow> | null;
    summary: StudentSummary | null;
    filters: ReportFilterState;
    table: TableState;
    formats: SelectOption[];
    filterOptions: ReportFilterOptions;
};

export default function ReportShow({
    type,
    columns,
    scope,
    rows,
    summary,
    filters,
    table,
    formats,
    filterOptions,
}: Props) {
    const baseUrl = `/reports/${type.value}`;
    const { search, setSearch, filterBy, goToPage, setPerPage } = useTableQuery(
        baseUrl,
        table,
    );

    const isStudentSummary = type.value === 'student_summary';

    // The download repeats whatever the screen is showing, so it reuses the URL.
    const downloadUrl = (format: string) => {
        const params = new URLSearchParams(
            Object.entries(filters)
                .filter(([, value]) => value !== null && value !== '')
                .map(([key, value]) => [key, String(value)]),
        );

        params.set('format', format);

        return `${baseUrl}/download?${params.toString()}`;
    };

    const downloadButtons = (
        isStudentSummary
            ? formats.filter((format) => format.value === 'pdf')
            : formats
    ).map((format) =>
        isStudentSummary && summary === null ? (
            <Button key={format.value} variant="outline" disabled>
                <Download aria-hidden="true" />
                Unduh {format.label}
            </Button>
        ) : (
            <Button key={format.value} variant="outline" asChild>
                <a href={downloadUrl(format.value)}>
                    <Download aria-hidden="true" />
                    Unduh {format.label}
                </a>
            </Button>
        ),
    );

    return (
        <AppLayout
            title={type.label}
            description={scope}
            actions={
                <div className="flex flex-wrap gap-2">{downloadButtons}</div>
            }
        >
            <div className="grid gap-4">
                {isStudentSummary ? (
                    <>
                        <Card>
                            <CardContent className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                                <ReportFilterBar
                                    options={filterOptions}
                                    filters={filters}
                                    isStudentSummary
                                    onFilterChange={filterBy}
                                />
                            </CardContent>
                        </Card>

                        {summary === null ? (
                            <Card>
                                <CardContent className="py-10 text-center text-sm text-muted-foreground">
                                    Pilih siswa untuk melihat rekap.
                                </CardContent>
                            </Card>
                        ) : (
                            <StudentSummaryCard summary={summary} />
                        )}
                    </>
                ) : (
                    rows !== null && (
                        <ReportTable
                            columns={columns}
                            rows={rows}
                            table={table}
                            search={search}
                            onSearchChange={setSearch}
                            onPageChange={goToPage}
                            onPerPageChange={setPerPage}
                            filters={
                                <ReportFilterBar
                                    options={filterOptions}
                                    filters={filters}
                                    isStudentSummary={false}
                                    onFilterChange={filterBy}
                                />
                            }
                        />
                    )
                )}
            </div>
        </AppLayout>
    );
}
