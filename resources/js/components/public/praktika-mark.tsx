type PraktikaMarkProps = {
    className?: string;
};

/**
 * The wordmark's glyph: three ledger rules with the last one checked off —
 * a journal entry that has been validated.
 */
export function PraktikaMark({ className }: PraktikaMarkProps) {
    return (
        <svg
            viewBox="0 0 32 32"
            aria-hidden="true"
            className={className}
            fill="none"
        >
            <rect width="32" height="32" rx="8" fill="var(--pk-ink)" />
            <path
                d="M8 11h16M8 16h16M8 21h7"
                stroke="var(--pk-sheet)"
                strokeWidth="2"
                strokeLinecap="round"
                opacity="0.55"
            />
            <path
                d="m17.5 21.5 2.5 2.5 5-5.5"
                stroke="var(--pk-stamp)"
                strokeWidth="2.4"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
