import { useEffect, useState } from 'react';

type State<T> = {
    data: T | null;
    loading: boolean;
    error: string | null;
};

/**
 * Lazily fetches a JSON resource when `url` is non-null (e.g. when a modal
 * opens). Resets to empty when `url` becomes null. The endpoint is expected to
 * return `{ data: T }`; the hook unwraps `.data`.
 */
export function useResource<T>(url: string | null): State<T> {
    const [state, setState] = useState<State<T>>({
        data: null,
        loading: false,
        error: null,
    });

    useEffect(() => {
        if (url === null) {
            setState({ data: null, loading: false, error: null });
            return;
        }

        let cancelled = false;
        setState({ data: null, loading: true, error: null });

        fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(async (res) => {
                if (!res.ok) {
                    throw new Error(`Gagal memuat data (${res.status}).`);
                }
                const body: { data: T } = await res.json();
                if (!cancelled) {
                    setState({ data: body.data, loading: false, error: null });
                }
            })
            .catch((err: Error) => {
                if (!cancelled) {
                    setState({ data: null, loading: false, error: err.message });
                }
            });

        return () => {
            cancelled = true;
        };
    }, [url]);

    return state;
}
