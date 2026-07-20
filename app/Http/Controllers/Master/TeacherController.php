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
use Illuminate\Http\JsonResponse;
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
            'stats' => $this->teachers->stats(),
        ]);
    }

    public function store(StoreTeacherRequest $request): RedirectResponse
    {
        $this->teachers->create(TeacherData::fromRequest($request));

        return redirect()
            ->route('master.teachers.index')
            ->with('success', 'Guru berhasil ditambahkan. Akun login dibuat — minta guru melakukan reset kata sandi untuk mengaktifkannya.');
    }

    public function show(Teacher $teacher): JsonResponse
    {
        $this->authorize('view', $teacher);

        $teacher->load('user:id,email,is_active,last_login_at');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $teacher,
        ]);
    }

    public function edit(Teacher $teacher): JsonResponse
    {
        $this->authorize('update', $teacher);

        $teacher->load('user:id,email');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $teacher,
        ]);
    }

    public function update(UpdateTeacherRequest $request, Teacher $teacher): RedirectResponse
    {
        $this->teachers->update($teacher, TeacherData::fromRequest($request));

        return redirect()
            ->route('master.teachers.index')
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
