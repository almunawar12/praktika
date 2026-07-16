import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { TableState } from '@/types/table';

type Params = Record<string, string | number | undefined>;

/**
 * Drives a server-side table: every change is a partial Inertia visit that re-runs
 * the query on the server. Nothing is filtered or sorted in the browser, so the
 * table behaves the same at 20 rows and at 20,000.
 */
export function useTableQuery(baseUrl: string, state: TableState) {
    const [search, setSearch] = useState(state.search ?? '');
    const isFirstRender = useRef(true);

    const visit = useCallback(
        (params: Params) => {
            const merged: Params = {
                search: search || undefined,
                sort: state.sort ?? undefined,
                direction: state.direction,
                per_page: state.per_page,
                ...state.filters,
                ...params,
            };

            // Drop empty values so the URL stays readable instead of accumulating
            // `?search=&sort=&filter=all` noise.
            const query = Object.fromEntries(
                Object.entries(merged).filter(
                    ([, value]) =>
                        value !== undefined && value !== '' && value !== 'all',
                ),
            );

            router.get(baseUrl, query as Record<string, string>, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [
            baseUrl,
            search,
            state.sort,
            state.direction,
            state.per_page,
            state.filters,
        ],
    );

    // Debounced search: without this every keystroke would be a round trip.
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        const timeout = setTimeout(() => {
            visit({ search: search || undefined, page: undefined });
        }, 300);

        return () => clearTimeout(timeout);
        // `visit` is intentionally omitted: it changes identity on every render and
        // would re-fire the timer in a loop.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const sortBy = useCallback(
        (column: string) => {
            const direction =
                state.sort === column && state.direction === 'asc'
                    ? 'desc'
                    : 'asc';

            visit({ sort: column, direction, page: undefined });
        },
        [state.sort, state.direction, visit],
    );

    const filterBy = useCallback(
        (key: string, value: string) => {
            // A filter change invalidates the current page: page 7 of the old result
            // set is usually empty in the new one.
            visit({ [key]: value, page: undefined });
        },
        [visit],
    );

    const setPerPage = useCallback(
        (value: string) => visit({ per_page: value, page: undefined }),
        [visit],
    );

    const goToPage = useCallback((page: number) => visit({ page }), [visit]);

    return { search, setSearch, sortBy, filterBy, setPerPage, goToPage };
}
