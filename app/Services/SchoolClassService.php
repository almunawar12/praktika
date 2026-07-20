<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\TableRequest;
use App\Exceptions\ResourceInUseException;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SchoolClassService
{
    public const SORTABLE = ['name', 'created_at'];

    /**
     * Totals for the overview cards, computed in one aggregate pass.
     *
     * @return array{total: int, active: int, inactive: int, students: int}
     */
    public function stats(): array
    {
        $counts = SchoolClass::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where is_active) as active')
            ->first();

        $total = (int) ($counts?->getAttribute('total') ?? 0);
        $active = (int) ($counts?->getAttribute('active') ?? 0);

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'students' => Student::query()->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, SchoolClass>
     */
    public function paginate(TableRequest $table): LengthAwarePaginator
    {
        return SchoolClass::query()
            ->with('department:id,code,name')
            ->withCount('students')
            ->when($table->search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhereHas('department', fn ($query) => $query
                            ->where('name', 'ilike', "%{$search}%")
                            ->orWhere('code', 'ilike', "%{$search}%"));
                });
            })
            ->when(
                $table->filter('department_id'),
                fn ($query, string $id) => $query->where('department_id', $id)
            )
            ->orderBy($table->sortColumn(self::SORTABLE, 'name'), $table->sortDirection())
            ->paginate($table->perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SchoolClass
    {
        return SchoolClass::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(SchoolClass $class, array $data): SchoolClass
    {
        $class->update($data);

        return $class;
    }

    /**
     * @throws ResourceInUseException
     */
    public function delete(SchoolClass $class): void
    {
        if ($class->students()->exists()) {
            throw new ResourceInUseException(
                'Kelas tidak dapat dihapus karena masih memiliki siswa.'
            );
        }

        $class->delete();
    }
}
