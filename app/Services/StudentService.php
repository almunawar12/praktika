<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\StudentData;
use App\DataTransferObjects\TableRequest;
use App\Enums\RoleName;
use App\Exceptions\ResourceInUseException;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StudentService
{
    public const SORTABLE = ['nis', 'nisn', 'name', 'created_at'];

    public function __construct(private readonly UserAccountService $accounts) {}

    /**
     * @return LengthAwarePaginator<int, Student>
     */
    public function paginate(TableRequest $table): LengthAwarePaginator
    {
        return Student::query()
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
            ->orderBy($table->sortColumn(self::SORTABLE, 'name'), $table->sortDirection())
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
