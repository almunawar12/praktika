<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\StudentData;
use App\DataTransferObjects\TableRequest;
use App\Enums\RoleName;
use App\Exceptions\ResourceInUseException;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StudentService
{
    public const SORTABLE = ['nis', 'nisn', 'name', 'created_at'];

    public function __construct(private readonly UserAccountService $accounts) {}

    /**
     * Totals for the overview cards, scoped to what the viewer may see.
     *
     * @return array{total: int, active: int, inactive: int, placed: int}
     */
    public function stats(User $viewer): array
    {
        $counts = Student::query()
            ->visibleTo($viewer)
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where exists (select 1 from users where users.id = students.user_id and users.is_active)) as active')
            ->selectRaw('count(*) filter (where exists (select 1 from placements where placements.student_id = students.id and placements.deleted_at is null)) as placed')
            ->first();

        $total = (int) ($counts?->getAttribute('total') ?? 0);
        $active = (int) ($counts?->getAttribute('active') ?? 0);

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'placed' => (int) ($counts?->getAttribute('placed') ?? 0),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Student>
     */
    public function paginate(TableRequest $table, User $viewer): LengthAwarePaginator
    {
        return Student::query()
            ->visibleTo($viewer)
            ->with(['user:id,email,is_active', 'schoolClass:id,name,department_id', 'schoolClass.department:id,code,name'])
            ->when($table->search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('nis', 'ilike', "%{$search}%")
                        ->orWhere('nisn', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn ($query) => $query->where('email', 'ilike', "%{$search}%"));
                });
            })
            ->when(
                $table->filter('class_id'),
                fn ($query, string $id) => $query->where('class_id', $id)
            )
            ->when($table->filter('department_id'), fn ($query, string $id) => $query
                ->whereHas('schoolClass', fn ($query) => $query->where('department_id', $id)))
            ->orderBy(...$table->sortOrder(self::SORTABLE))
            ->paginate($table->perPage)
            ->withQueryString();
    }

    public function create(StudentData $data): Student
    {
        return DB::transaction(function () use ($data): Student {
            $user = $this->accounts->createFor(
                RoleName::Siswa,
                $data->name,
                $data->email,
                $data->phone,
            );

            return Student::create([
                'user_id' => $user->id,
                'class_id' => $data->classId,
                'nis' => $data->nis,
                'nisn' => $data->nisn,
                'name' => $data->name,
                'phone' => $data->phone,
            ]);
        });
    }

    public function update(Student $student, StudentData $data): Student
    {
        return DB::transaction(function () use ($student, $data): Student {
            $student->update([
                'class_id' => $data->classId,
                'nis' => $data->nis,
                'nisn' => $data->nisn,
                'name' => $data->name,
                'phone' => $data->phone,
            ]);

            $this->accounts->syncFromProfile(
                $student->user,
                $data->name,
                $data->email,
                $data->phone,
            );

            return $student;
        });
    }

    /**
     * @throws ResourceInUseException
     */
    public function delete(Student $student): void
    {
        // placements.student_id is RESTRICT. A student who has been placed is part of
        // the PKL record and must not vanish from under it.
        if ($student->placements()->exists()) {
            throw new ResourceInUseException(
                'Siswa tidak dapat dihapus karena sudah memiliki riwayat penempatan PKL.'
            );
        }

        DB::transaction(function () use ($student): void {
            $this->accounts->deactivate($student->user);

            $student->delete();
        });
    }
}
