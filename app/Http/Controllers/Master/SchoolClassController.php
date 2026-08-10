<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\DataTransferObjects\TableRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSchoolClassRequest;
use App\Http\Requests\Master\UpdateSchoolClassRequest;
use App\Models\AcademicYear;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Services\SchoolClassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolClassController extends Controller
{
    public function __construct(private readonly SchoolClassService $classes) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', SchoolClass::class);

        $table = TableRequest::fromRequest($request, ['department_id']);

        return Inertia::render('master/classes/index', [
            'classes' => $this->classes->paginate($table),
            'table' => $table->toArray(),
            'sortable' => SchoolClassService::SORTABLE,
            'departments' => $this->departmentOptions(),
            'academicYears' => $this->academicYearOptions(),
            'stats' => $this->classes->stats(),
        ]);
    }

    public function store(StoreSchoolClassRequest $request): RedirectResponse
    {
        $this->classes->create($request->validated());

        return redirect()
            ->route('master.classes.index')
            ->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function show(SchoolClass $class): JsonResponse
    {
        $this->authorize('view', $class);

        $class->load(['department:id,code,name', 'academicYear:id,name']);
        $class->loadCount('students');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $class,
        ]);
    }

    public function edit(SchoolClass $class): JsonResponse
    {
        $this->authorize('update', $class);

        $class->load('department:id,code,name');

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $class,
        ]);
    }

    public function update(UpdateSchoolClassRequest $request, SchoolClass $class): RedirectResponse
    {
        $this->classes->update($class, $request->validated());

        return redirect()
            ->route('master.classes.index')
            ->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(SchoolClass $class): RedirectResponse
    {
        $this->authorize('delete', $class);

        $this->classes->delete($class);

        return redirect()
            ->route('master.classes.index')
            ->with('success', 'Kelas berhasil dihapus.');
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

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function academicYearOptions(): array
    {
        return AcademicYear::query()
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'is_active'])
            ->map(fn (AcademicYear $year): array => [
                'value' => $year->id,
                'label' => $year->is_active ? "{$year->name} (aktif)" : $year->name,
            ])
            ->all();
    }
}
