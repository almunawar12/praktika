type PraktikaMarkProps = {
    className?: string;
};

export function PraktikaMark({ className }: PraktikaMarkProps) {
    return (
        <img
            src="/praktika-logo.png"
            alt=""
            aria-hidden="true"
            className={className}
        />
    );
}
