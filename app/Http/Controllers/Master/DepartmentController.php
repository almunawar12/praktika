<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\DataTransferObjects\TableRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreDepartmentRequest;
use App\Http\Requests\Master\UpdateDepartmentRequest;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentService $departments) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Department::class);

        $table = TableRequest::fromRequest($request);

        return Inertia::render('master/departments/index', [
            'departments' => $this->departments->paginate($table),
            'table' => $table->toArray(),
            'sortable' => DepartmentService::SORTABLE,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Department::class);

        return Inertia::render('master/departments/create');
    }

    public function store(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = $this->departments->create($request->validated());

        return redirect()
            ->route('master.departments.show', $department)
            ->with('success', 'Jurusan berhasil ditambahkan.');
    }

    public function show(Department $department): Response
    {
        $this->authorize('view', $department);

        $department->loadCount('schoolClasses');
        $department->load('schoolClasses:id,department_id,name');

        return Inertia::render('master/departments/show', [
            'department' => $department,
        ]);
    }

    public function edit(Department $department): Response
    {
        $this->authorize('update', $department);

        return Inertia::render('master/departments/edit', [
            'department' => $department,
        ]);
    }

    public function update(UpdateDepartmentRequest $request, Department $department): RedirectResponse
    {
        $this->departments->update($department, $request->validated());

        return redirect()
            ->route('master.departments.show', $department)
            ->with('success', 'Jurusan berhasil diperbarui.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        $this->authorize('delete', $department);

        // ResourceInUseException renders itself as a flash message.
        $this->departments->delete($department);

        return redirect()
            ->route('master.departments.index')
            ->with('success', 'Jurusan berhasil dihapus.');
    }
}
