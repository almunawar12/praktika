<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\TableRequest;
use App\Exceptions\ResourceInUseException;
use App\Models\AcademicYear;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AcademicYearService
{
    public const SORTABLE = ['name', 'start_date', 'end_date', 'created_at'];

    /**
     * @return array{total: int, active: int}
     */
    public function stats(): array
    {
        $counts = AcademicYear::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where is_active) as active')
            ->first();

        return [
            'total' => (int) ($counts?->getAttribute('total') ?? 0),
            'active' => (int) ($counts?->getAttribute('active') ?? 0),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, AcademicYear>
     */
    public function paginate(TableRequest $table): LengthAwarePaginator
    {
        return AcademicYear::query()
            ->withCount(['schoolClasses', 'placements'])
            ->when($table->search, fn ($query, string $search) => $query
                ->where('name', 'ilike', "%{$search}%"))
            ->orderBy(...$table->sortOrder(self::SORTABLE))
            ->paginate($table->perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): AcademicYear
    {
        // Always starts inactive: `activate()` is the only path to `is_active`, so
        // the one-active-year guarantee has a single place to hold.
        return AcademicYear::create([...$data, 'is_active' => false]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(AcademicYear $academicYear, array $data): AcademicYear
    {
        $academicYear->update($data);

        return $academicYear;
    }

    /**
     * @throws ResourceInUseException
     */
    public function delete(AcademicYear $academicYear): void
    {
        // placements.academic_year_id is RESTRICT, so without this the delete would
        // surface as a raw SQL error instead of something an admin can act on.
        if ($academicYear->placements()->exists()) {
            throw new ResourceInUseException(
                'Tahun ajaran tidak dapat dihapus karena masih memiliki penempatan PKL.'
            );
        }

        $academicYear->delete();
    }

    /**
     * Makes this the current academic year and no other. The partial unique index
     * on `is_active` is the real guarantee; this transaction is what makes the
     * hand-off look atomic to a reader.
     */
    public function activate(AcademicYear $academicYear): AcademicYear
    {
        DB::transaction(function () use ($academicYear): void {
            AcademicYear::query()->where('is_active', true)->update(['is_active' => false]);

            $academicYear->update(['is_active' => true]);
        });

        return $academicYear;
    }
}
