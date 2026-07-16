import { Search, X } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { SelectOption } from '@/types/table';

export type Filter = {
    key: string;
    label: string;
    value: string;
    options: SelectOption[];
};

type ToolbarProps = {
    search: string;
    onSearchChange: (value: string) => void;
    searchPlaceholder?: string;
    filters?: Filter[];
    onFilterChange?: (key: string, value: string) => void;
    children?: ReactNode;
};

export function DataTableToolbar({
    search,
    onSearchChange,
    searchPlaceholder = 'Cari...',
    filters = [],
    onFilterChange,
    children,
}: ToolbarProps) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                <div className="relative w-full sm:max-w-xs">
                    <Search
                        className="absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        type="search"
                        value={search}
                        onChange={(event) => onSearchChange(event.target.value)}
                        placeholder={searchPlaceholder}
                        className="pl-8"
                        aria-label={searchPlaceholder}
                    />
                    {search ? (
                        <Button
                            variant="ghost"
                            size="icon"
                            className="absolute top-1/2 right-0.5 size-8 -translate-y-1/2"
                            onClick={() => onSearchChange('')}
                        >
                            <X className="size-3.5" aria-hidden="true" />
                            <span className="sr-only">Hapus pencarian</span>
                        </Button>
                    ) : null}
                </div>

                {filters.map((filter) => (
                    <Select
                        key={filter.key}
                        value={filter.value || 'all'}
                        onValueChange={(value) =>
                            onFilterChange?.(filter.key, value)
                        }
                    >
                        <SelectTrigger
                            className="w-full sm:w-48"
                            aria-label={filter.label}
                        >
                            <SelectValue placeholder={filter.label} />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                Semua {filter.label}
                            </SelectItem>
                            {filter.options.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                ))}
            </div>

            {children ? (
                <div className="flex items-center gap-2">{children}</div>
            ) : null}
        </div>
    );
}
