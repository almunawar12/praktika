<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\DataTransferObjects\StudentData;
use App\DataTransferObjects\TableRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreStudentRequest;
use App\Http\Requests\Master\UpdateStudentRequest;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Services\StudentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(private readonly StudentService $students) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Student::class);

        $table = TableRequest::fromRequest($request, ['class_id', 'department_id']);

        return Inertia::render('master/students/index', [
            'students' => $this->students->paginate($table),
            'table' => $table->toArray(),
            'sortable' => StudentService::SORTABLE,
            'classes' => $this->classOptions(),
            'departments' => $this->departmentOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Student::class);

        return Inertia::render('master/students/create', [
            'classes' => $this->classOptions(),
        ]);
    }

    public function store(StoreStudentRequest $request): RedirectResponse
    {
        $student = $this->students->create(StudentData::fromRequest($request));

        return redirect()
            ->route('master.students.show', $student)
            ->with('success', 'Siswa berhasil ditambahkan. Akun login dibuat — minta siswa melakukan reset kata sandi untuk mengaktifkannya.');
    }

    public function show(Student $student): Response
    {
        $this->authorize('view', $student);

        $student->load([
            'user:id,email,is_active,last_login_at',
            'schoolClass:id,name,department_id',
            'schoolClass.department:id,code,name',
        ]);

        return Inertia::render('master/students/show', [
            'student' => $student,
        ]);
    }

    public function edit(Student $student): Response
    {
        $this->authorize('update', $student);

        $student->load('user:id,email');

        return Inertia::render('master/students/edit', [
            'student' => $student,
            'classes' => $this->classOptions(),
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student): RedirectResponse
    {
        $this->students->update($student, StudentData::fromRequest($request));

        return redirect()
            ->route('master.students.show', $student)
            ->with('success', 'Siswa berhasil diperbarui.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->authorize('delete', $student);

        $this->students->delete($student);

        return redirect()
            ->route('master.students.index')
            ->with('success', 'Siswa berhasil dihapus dan akunnya dinonaktifkan.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function classOptions(): array
    {
        return SchoolClass::query()
            ->with('department:id,code')
            ->orderBy('name')
            ->get(['id', 'name', 'department_id'])
            ->map(fn (SchoolClass $c): array => [
                'value' => $c->id,
                'label' => "{$c->name} ({$c->department->code})",
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function departmentOptions(): array
    {
        return Department::query()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Department $d): array => [
                'value' => $d->id,
                'label' => "{$d->code} — {$d->name}",
            ])
            ->all();
    }
}
