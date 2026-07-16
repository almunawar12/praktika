<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\DataTransferObjects\TableRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSchoolClassRequest;
use App\Http\Requests\Master\UpdateSchoolClassRequest;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Services\SchoolClassService;
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
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', SchoolClass::class);

        return Inertia::render('master/classes/create', [
            'departments' => $this->departmentOptions(),
        ]);
    }

    public function store(StoreSchoolClassRequest $request): RedirectResponse
    {
        $class = $this->classes->create($request->validated());

        return redirect()
            ->route('master.classes.show', $class)
            ->with('success', 'Kelas berhasil ditambahkan.');
    }

    public function show(SchoolClass $class): Response
    {
        $this->authorize('view', $class);

        $class->load('department:id,code,name');
        $class->loadCount('students');

        return Inertia::render('master/classes/show', [
            'schoolClass' => $class,
        ]);
    }

    public function edit(SchoolClass $class): Response
    {
        $this->authorize('update', $class);

        return Inertia::render('master/classes/edit', [
            'schoolClass' => $class,
            'departments' => $this->departmentOptions(),
        ]);
    }

    public function update(UpdateSchoolClassRequest $request, SchoolClass $class): RedirectResponse
    {
        $this->classes->update($class, $request->validated());

        return redirect()
            ->route('master.classes.show', $class)
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
}
