<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Illuminate\Http\Request;

/**
 * Normalised query-string state for a server-side DataTable.
 *
 * Everything here comes from the URL, so nothing is trusted: `sort` is matched
 * against a caller-supplied allow-list before it can reach an ORDER BY, and
 * `perPage` is clamped. Interpolating either straight into SQL would be an
 * injection hole and an easy way to let a client ask for 1,000,000 rows.
 */
final readonly class TableRequest
{
    public const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    /**
     * @param  array<string, string>  $filters
     */
    public function __construct(
        public ?string $search,
        public ?string $sort,
        public string $direction,
        public int $perPage,
        public array $filters,
    ) {}

    /**
     * @param  array<int, string>  $filterKeys
     */
    public static function fromRequest(Request $request, array $filterKeys = []): self
    {
        $search = trim((string) $request->string('search'));

        $filters = [];

        foreach ($filterKeys as $key) {
            $value = trim((string) $request->string($key));

            if ($value !== '' && $value !== 'all') {
                $filters[$key] = $value;
            }
        }

        return new self(
            search: $search === '' ? null : $search,
            sort: $request->string('sort')->toString() ?: null,
            direction: $request->string('direction')->toString() === 'desc' ? 'desc' : 'asc',
            perPage: max(5, min(self::MAX_PER_PAGE, $request->integer('per_page', self::DEFAULT_PER_PAGE))),
            filters: $filters,
        );
    }

    /**
     * Resolve the sort column against an allow-list.
     *
     * @param  array<int, string>  $allowed
     */
    public function sortColumn(array $allowed, string $default): string
    {
        return in_array($this->sort, $allowed, true) ? (string) $this->sort : $default;
    }

    /**
     * The direction narrowed to the two literals `orderBy()` accepts.
     *
     * @return 'asc'|'desc'
     */
    public function sortDirection(): string
    {
        return $this->direction === 'desc' ? 'desc' : 'asc';
    }

    /**
     * The `[column, direction]` for a list's ORDER BY. When the user has chosen a
     * sortable column it wins; otherwise the list falls back to newest-first, so
     * freshly created rows sit at the top of every table.
     *
     * @param  array<int, string>  $allowed
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public function sortOrder(array $allowed): array
    {
        if (in_array($this->sort, $allowed, true)) {
            return [(string) $this->sort, $this->sortDirection()];
        }

        return ['created_at', 'desc'];
    }

    public function filter(string $key): ?string
    {
        return $this->filters[$key] ?? null;
    }

    /**
     * The state echoed back to the frontend so the table can render its controls.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'search' => $this->search,
            'sort' => $this->sort,
            'direction' => $this->direction,
            'per_page' => $this->perPage,
            'filters' => (object) $this->filters,
        ];
    }
}
