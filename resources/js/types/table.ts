/**
 * Mirrors Laravel's LengthAwarePaginator JSON shape.
 */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

/**
 * Mirrors App\DataTransferObjects\TableRequest::toArray().
 */
export type TableState = {
    search: string | null;
    sort: string | null;
    direction: 'asc' | 'desc';
    per_page: number;
    filters: Record<string, string>;
};

export type SelectOption = {
    value: string;
    label: string;
};
