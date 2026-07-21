import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type { SelectOption } from '@/types/table';

type ComboboxProps = {
    value: string;
    onValueChange: (value: string) => void;
    options: SelectOption[];
    /** Trigger text when nothing is selected. */
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    id?: string;
    disabled?: boolean;
    className?: string;
    /** Mirrors the Select API so it can drop into a Field with error styling. */
    'aria-invalid'?: boolean;
};

/**
 * A single-select dropdown with a search box — the same shape as our Select
 * (`value` / `onValueChange` / `SelectOption[]`), for pickers with many options
 * where scanning a plain list is slow (students, companies, ...).
 */
export function Combobox({
    value,
    onValueChange,
    options,
    placeholder = 'Pilih...',
    searchPlaceholder = 'Cari...',
    emptyText = 'Tidak ada hasil.',
    id,
    disabled = false,
    className,
    'aria-invalid': ariaInvalid,
}: ComboboxProps) {
    const [open, setOpen] = useState(false);
    const selected = options.find((option) => option.value === value);

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-invalid={ariaInvalid}
                    disabled={disabled}
                    className={cn(
                        'w-full justify-between font-normal',
                        selected ? '' : 'text-muted-foreground',
                        className,
                    )}
                >
                    <span className="truncate">
                        {selected ? selected.label : placeholder}
                    </span>
                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-[--radix-popover-trigger-width] p-0"
            >
                <Command
                    filter={(itemValue, searchTerm) =>
                        itemValue
                            .toLowerCase()
                            .includes(searchTerm.toLowerCase())
                            ? 1
                            : 0
                    }
                >
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyText}</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    // cmdk searches this string; use the label so
                                    // typing matches what the user sees.
                                    value={option.label}
                                    onSelect={() => {
                                        onValueChange(option.value);
                                        setOpen(false);
                                    }}
                                >
                                    <Check
                                        className={cn(
                                            'size-4',
                                            option.value === value
                                                ? 'opacity-100'
                                                : 'opacity-0',
                                        )}
                                    />
                                    <span className="truncate">
                                        {option.label}
                                    </span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
