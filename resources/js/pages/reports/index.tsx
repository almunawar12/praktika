import { Form, router } from '@inertiajs/react';
import { Download, FileDown, LoaderCircle, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { DataTable } from '@/components/data-table/data-table';
import type { Column } from '@/components/data-table/data-table';
import { DataTablePagination } from '@/components/data-table/data-table-pagination';
import { Combobox } from '@/components/shared/combobox';
import { Field } from '@/components/shared/field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTableQuery } from '@/hooks/use-table-query';
import AppLayout from '@/layouts/app-layout';
import type { Paginated, SelectOption, TableState } from '@/types/table';

type Status = 'pending' | 'processing' | 'completed' | 'failed';

type ReportRow = {
    id: string;
    type: string;
    format: string;
    status: Status;
    row_count: number | null;
    file_name: string | null;
    error: string | null;
    created_at: string;
    completed_at: string | null;
};

type Props = {
    exports: Paginated<ReportRow>;
    table: TableState;
    initialType: string | null;
    types: SelectOption[];
    formats: SelectOption[];
    filterOptions: {
        departments: { id: string; name: string }[];
        companies: { id: string; name: string }[];
    };
    students: SelectOption[];
};

const NONE = 'all';

const STATUS_META: Record<Status, { label: string; className: string }> = {
    pending: { label: 'Menunggu', className: 'bg-muted text-muted-foreground' },
    processing: {
        label: 'Diproses',
        className: 'bg-amber-500/15 text-amber-700 dark:text-amber-400',
    },
    completed: {
        label: 'Selesai',
        className: 'bg-emerald-600/15 text-emerald-700 dark:text-emerald-400',
    },
    failed: {
        label: 'Gagal',
        className: 'bg-destructive/15 text-destructive',
    },
};

function formatDate(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
}

export default function ReportIndex({
    exports,
    table,
    initialType,
    types,
    formats,
    filterOptions,
    students,
}: Props) {
    const { sortBy, goToPage, setPerPage } = useTableQuery('/reports', table);

    const typeLabels = new Map(types.map((t) => [t.value, t.label]));

    const [type, setType] = useState(initialType ?? types[0]?.value ?? '');
    const [format, setFormat] = useState(formats[0]?.value ?? 'pdf');
    const [departmentId, setDepartmentId] = useState(NONE);
    const [companyId, setCompanyId] = useState(NONE);
    const [studentId, setStudentId] = useState('');

    const isStudentSummary = type === 'student_summary';

    const columns: Column<ReportRow>[] = [
        {
            key: 'type',
            header: 'Laporan',
            cell: (row) => (
                <div className="grid gap-0.5">
                    <span className="font-medium">
                        {typeLabels.get(row.type) ?? row.type}
                    </span>
                    <span className="text-xs text-muted-foreground uppercase">
                        {row.format}
                        {row.row_count !== null
                            ? ` · ${row.row_count} baris`
                            : ''}
                    </span>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            cell: (row) => (
                <div className="grid gap-0.5">
                    <Badge
                        className={`border-transparent ${STATUS_META[row.status].className}`}
                    >
                        {STATUS_META[row.status].label}
                    </Badge>
                    {row.status === 'failed' && row.error ? (
                        <span className="text-xs text-destructive">
                            {row.error}
                        </span>
                    ) : null}
                </div>
            ),
        },
        {
            key: 'created_at',
            header: 'Dibuat',
            hideOnMobile: true,
            cell: (row) => (
                <span className="text-sm text-muted-foreground">
                    {formatDate(row.created_at)}
                </span>
            ),
        },
        {
            key: 'actions',
            header: '',
            className: 'w-28',
            cell: (row) => (
                <div className="flex justify-end gap-1">
                    {row.status === 'completed' ? (
                        <Button variant="ghost" size="sm" asChild>
                            <a href={`/reports/${row.id}/download`}>
                                <Download
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                <span className="sr-only">Unduh</span>
                            </a>
                        </Button>
                    ) : null}
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() =>
                            router.delete(`/reports/${row.id}`, {
                                preserveScroll: true,
                            })
                        }
                    >
                        <Trash2 className="size-4" aria-hidden="true" />
                        <span className="sr-only">Hapus</span>
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <AppLayout
            title="Laporan"
            description="Buat dan unduh rekap PKL dalam PDF atau Excel"
        >
            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader>
                        <CardTitle>Buat Laporan</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            action="/reports"
                            method="post"
                            options={{ preserveScroll: true }}
                            onSuccess={() => {
                                setDepartmentId(NONE);
                                setCompanyId(NONE);
                                setStudentId('');
                            }}
                        >
                            {({ processing, errors }) => (
                                <div className="grid gap-4">
                                    <input
                                        type="hidden"
                                        name="type"
                                        value={type}
                                    />
                                    <Field
                                        id="type"
                                        label="Jenis Laporan"
                                        error={errors.type}
                                        required
                                    >
                                        <Select
                                            value={type}
                                            onValueChange={setType}
                                        >
                                            <SelectTrigger id="type">
                                                <SelectValue placeholder="Pilih laporan" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {types.map((option) => (
                                                    <SelectItem
                                                        key={option.value}
                                                        value={option.value}
                                                    >
                                                        {option.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>

                                    {isStudentSummary ? (
                                        <>
                                            <input
                                                type="hidden"
                                                name="format"
                                                value="pdf"
                                            />
                                            <input
                                                type="hidden"
                                                name="student_id"
                                                value={studentId}
                                            />
                                            <Field
                                                id="student_id"
                                                label="Siswa"
                                                error={errors.student_id}
                                                required
                                            >
                                                <Combobox
                                                    id="student_id"
                                                    value={studentId}
                                                    onValueChange={
                                                        setStudentId
                                                    }
                                                    options={students}
                                                    placeholder="Pilih siswa"
                                                    searchPlaceholder="Cari nama/NIS..."
                                                    emptyText="Tidak ada siswa."
                                                    aria-invalid={
                                                        !!errors.student_id
                                                    }
                                                />
                                            </Field>
                                        </>
                                    ) : (
                                        <>
                                            <input
                                                type="hidden"
                                                name="format"
                                                value={format}
                                            />
                                            <Field
                                                id="format"
                                                label="Format"
                                                error={errors.format}
                                                required
                                            >
                                                <Select
                                                    value={format}
                                                    onValueChange={setFormat}
                                                >
                                                    <SelectTrigger id="format">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {formats.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </Field>
                                        </>
                                    )}

                                    {!isStudentSummary &&
                                    filterOptions.departments.length > 0 ? (
                                        <Field
                                            id="department_id"
                                            label="Jurusan (opsional)"
                                            error={errors.department_id}
                                        >
                                            <input
                                                type="hidden"
                                                name="department_id"
                                                value={
                                                    departmentId === NONE
                                                        ? ''
                                                        : departmentId
                                                }
                                            />
                                            <Select
                                                value={departmentId}
                                                onValueChange={setDepartmentId}
                                            >
                                                <SelectTrigger id="department_id">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value={NONE}>
                                                        Semua jurusan
                                                    </SelectItem>
                                                    {filterOptions.departments.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={option.id}
                                                                value={
                                                                    option.id
                                                                }
                                                            >
                                                                {option.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                    ) : null}

                                    {!isStudentSummary &&
                                    filterOptions.companies.length > 0 ? (
                                        <Field
                                            id="company_id"
                                            label="Perusahaan (opsional)"
                                            error={errors.company_id}
                                        >
                                            <input
                                                type="hidden"
                                                name="company_id"
                                                value={
                                                    companyId === NONE
                                                        ? ''
                                                        : companyId
                                                }
                                            />
                                            <Select
                                                value={companyId}
                                                onValueChange={setCompanyId}
                                            >
                                                <SelectTrigger id="company_id">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value={NONE}>
                                                        Semua perusahaan
                                                    </SelectItem>
                                                    {filterOptions.companies.map(
                                                        (option) => (
                                                            <SelectItem
                                                                key={option.id}
                                                                value={
                                                                    option.id
                                                                }
                                                            >
                                                                {option.name}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                    ) : null}

                                    <div className="grid grid-cols-2 gap-3">
                                        <Field
                                            id="date_from"
                                            label="Dari"
                                            error={errors.date_from}
                                        >
                                            <Input
                                                id="date_from"
                                                name="date_from"
                                                type="date"
                                            />
                                        </Field>
                                        <Field
                                            id="date_to"
                                            label="Sampai"
                                            error={errors.date_to}
                                        >
                                            <Input
                                                id="date_to"
                                                name="date_to"
                                                type="date"
                                            />
                                        </Field>
                                    </div>

                                    <Button
                                        type="submit"
                                        disabled={
                                            processing ||
                                            type === '' ||
                                            (isStudentSummary &&
                                                studentId === '')
                                        }
                                    >
                                        {processing ? (
                                            <LoaderCircle
                                                className="size-4 animate-spin"
                                                aria-hidden="true"
                                            />
                                        ) : (
                                            <FileDown
                                                className="size-4"
                                                aria-hidden="true"
                                            />
                                        )}
                                        Buat Laporan
                                    </Button>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                <div className="flex flex-col gap-4 lg:col-span-2">
                    <DataTable
                        columns={columns}
                        page={exports}
                        state={table}
                        onSort={sortBy}
                        rowKey={(row) => row.id}
                        emptyMessage="Belum ada laporan. Buat satu di sebelah kiri."
                    />

                    <DataTablePagination
                        page={exports}
                        onPageChange={goToPage}
                        onPerPageChange={setPerPage}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
