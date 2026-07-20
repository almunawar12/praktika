import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SelectOption } from '@/types/table';

type FilterSelectProps = {
    value: string;
    onValueChange: (value: string) => void;
    options: SelectOption[];
    /** The "no filter" choice, e.g. "Semua status". */
    allLabel: string;
    label: string;
    className?: string;
};

export function FilterSelect({
    value,
    onValueChange,
    options,
    allLabel,
    label,
    className = 'sm:w-40',
}: FilterSelectProps) {
    return (
        <Select value={value} onValueChange={onValueChange}>
            <SelectTrigger className={className} aria-label={label}>
                <SelectValue placeholder={allLabel} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">{allLabel}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
