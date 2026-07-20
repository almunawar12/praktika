<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\TableRequest;
use App\Exceptions\ResourceInUseException;
use App\Models\Department;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DepartmentService
{
    public const SORTABLE = ['code', 'name', 'created_at'];

    /**
     * Totals for the overview cards, computed in one aggregate pass.
     *
     * @return array{total: int, active: int, inactive: int, students: int}
     */
    public function stats(): array
    {
        $counts = Department::query()
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
     * @return LengthAwarePaginator<int, Department>
     */
    public function paginate(TableRequest $table): LengthAwarePaginator
    {
        return Department::query()
            ->withCount(['schoolClasses', 'students'])
            ->when($table->search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('code', 'ilike', "%{$search}%");
                });
            })
            ->orderBy($table->sortColumn(self::SORTABLE, 'name'), $table->sortDirection())
            ->paginate($table->perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Department
    {
        return Department::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Department $department, array $data): Department
    {
        $department->update($data);

        return $department;
    }

    /**
     * @throws ResourceInUseException
     */
    public function delete(Department $department): void
    {
        // school_classes.department_id is RESTRICT, so without this the delete would
        // surface as a raw SQL error instead of something an admin can act on.
        if ($department->schoolClasses()->exists()) {
            throw new ResourceInUseException(
                'Jurusan tidak dapat dihapus karena masih memiliki kelas.'
            );
        }

        $department->delete();
    }
}
