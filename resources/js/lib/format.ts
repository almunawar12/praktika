const rtf = new Intl.RelativeTimeFormat('id', { numeric: 'auto' });

/**
 * "5 jam yang lalu"-style label for list rows. Coarse on purpose: the rows
 * link to the module where exact timestamps live.
 */
export function formatRelativeTime(iso: string | null): string {
    if (iso === null) {
        return '-';
    }

    const minutes = Math.round((Date.now() - new Date(iso).getTime()) / 60_000);

    if (minutes < 60) {
        return rtf.format(-Math.max(minutes, 0), 'minute');
    }

    const hours = Math.round(minutes / 60);

    if (hours < 24) {
        return rtf.format(-hours, 'hour');
    }

    return rtf.format(-Math.round(hours / 24), 'day');
}
