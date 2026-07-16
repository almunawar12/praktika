<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\DataTransferObjects\TableRequest;
use App\DataTransferObjects\TeacherData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreTeacherRequest;
use App\Http\Requests\Master\UpdateTeacherRequest;
use App\Models\Teacher;
use App\Services\TeacherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function __construct(private readonly TeacherService $teachers) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Teacher::class);

        $table = TableRequest::fromRequest($request, ['is_active']);

        return Inertia::render('master/teachers/index', [
            'teachers' => $this->teachers->paginate($table),
            'table' => $table->toArray(),
            'sortable' => TeacherService::SORTABLE,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Teacher::class);

        return Inertia::render('master/teachers/create');
    }

    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        $teacher = $this->teachers->create(TeacherData::fromRequest($request));

        return redirect()
            ->route('master.teachers.show', $teacher)
            ->with('success', 'Guru berhasil ditambahkan. Akun login dibuat — minta guru melakukan reset kata sandi untuk mengaktifkannya.');
    }

    public function show(Teacher $teacher): Response
    {
        $this->authorize('view', $teacher);

        $teacher->load('user:id,email,is_active,last_login_at');

        return Inertia::render('master/teachers/show', [
            'teacher' => $teacher,
        ]);
    }

    public function edit(Teacher $teacher): Response
    {
        $this->authorize('update', $teacher);

        $teacher->load('user:id,email');

        return Inertia::render('master/teachers/edit', [
            'teacher' => $teacher,
        ]);
    }

    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $this->teachers->update($teacher, TeacherData::fromRequest($request));

        return redirect()
            ->route('master.teachers.show', $teacher)
            ->with('success', 'Guru berhasil diperbarui.');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        $this->authorize('delete', $teacher);

        $this->teachers->delete($teacher);

        return redirect()
            ->route('master.teachers.index')
            ->with('success', 'Guru berhasil dihapus dan akunnya dinonaktifkan.');
    }
}
