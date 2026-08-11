import { FilterSelect } from '@/components/data-table/filter-select';
import { Combobox } from '@/components/shared/combobox';
import { Input } from '@/components/ui/input';
import type { SelectOption } from '@/types/table';

export type ReportFilterOptions = {
    departments: { id: string; name: string }[];
    classes: { id: string; name: string }[];
    companies: { id: string; name: string }[];
    teachers: { id: string; name: string }[];
    students: SelectOption[];
};

export type ReportFilterState = {
    date_from: string | null;
    date_to: string | null;
    department_id: string | null;
    company_id: string | null;
    school_class_id: string | null;
    teacher_id: string | null;
    student_id: string | null;
};

type ReportFilterBarProps = {
    options: ReportFilterOptions;
    filters: ReportFilterState;
    isStudentSummary: boolean;
    onFilterChange: (key: string, value: string) => void;
};

const ALL = 'all';

function toOptions(rows: { id: string; name: string }[]): SelectOption[] {
    return rows.map((row) => ({ value: row.id, label: row.name }));
}

/**
 * The whole report scope in one row of controls. Every change is a visit, so the
 * URL is always the report the user is looking at.
 */
export function ReportFilterBar({
    options,
    filters,
    isStudentSummary,
    onFilterChange,
}: ReportFilterBarProps) {
    return (
        <>
            <Input
                type="date"
                value={filters.date_from ?? ''}
                onChange={(event) =>
                    onFilterChange('date_from', event.target.value)
                }
                className="sm:w-40"
                aria-label="Tanggal mulai"
            />
            <Input
                type="date"
                value={filters.date_to ?? ''}
                onChange={(event) =>
                    onFilterChange('date_to', event.target.value)
                }
                className="sm:w-40"
                aria-label="Tanggal akhir"
            />

            {options.departments.length > 0 && (
                <FilterSelect
                    value={filters.department_id ?? ALL}
                    onValueChange={(value) =>
                        onFilterChange('department_id', value)
                    }
                    options={toOptions(options.departments)}
                    allLabel="Semua jurusan"
                    label="Filter jurusan"
                />
            )}

            {options.classes.length > 0 && (
                <FilterSelect
                    value={filters.school_class_id ?? ALL}
                    onValueChange={(value) =>
                        onFilterChange('school_class_id', value)
                    }
                    options={toOptions(options.classes)}
                    allLabel="Semua kelas"
                    label="Filter kelas"
                />
            )}

            {options.companies.length > 0 && (
                <FilterSelect
                    value={filters.company_id ?? ALL}
                    onValueChange={(value) =>
                        onFilterChange('company_id', value)
                    }
                    options={toOptions(options.companies)}
                    allLabel="Semua perusahaan"
                    label="Filter perusahaan"
                />
            )}

            {options.teachers.length > 0 && (
                <FilterSelect
                    value={filters.teacher_id ?? ALL}
                    onValueChange={(value) =>
                        onFilterChange('teacher_id', value)
                    }
                    options={toOptions(options.teachers)}
                    allLabel="Semua guru"
                    label="Filter guru pembimbing"
                />
            )}

            {isStudentSummary && (
                <Combobox
                    value={filters.student_id ?? ''}
                    onValueChange={(value) =>
                        onFilterChange('student_id', value)
                    }
                    options={options.students}
                    placeholder="Pilih siswa"
                    searchPlaceholder="Cari nama atau NIS"
                    emptyText="Siswa tidak ditemukan."
                    className="sm:w-72"
                />
            )}
        </>
    );
}
