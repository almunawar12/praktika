<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\DataTransferObjects\TableRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreAcademicYearRequest;
use App\Http\Requests\Master\UpdateAcademicYearRequest;
use App\Models\AcademicYear;
use App\Services\AcademicYearService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function __construct(private readonly AcademicYearService $academicYears) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AcademicYear::class);

        $table = TableRequest::fromRequest($request);

        return Inertia::render('master/academic-years/index', [
            'academicYears' => $this->academicYears->paginate($table),
            'table' => $table->toArray(),
            'sortable' => AcademicYearService::SORTABLE,
            'stats' => $this->academicYears->stats(),
        ]);
    }

    public function store(StoreAcademicYearRequest $request): RedirectResponse
    {
        $this->academicYears->create($request->validated());

        return redirect()
            ->route('master.academic-years.index')
            ->with('success', 'Tahun ajaran berhasil ditambahkan.');
    }

    public function show(AcademicYear $academicYear): JsonResponse
    {
        $this->authorize('view', $academicYear);

        $academicYear->loadCount(['schoolClasses', 'placements']);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $academicYear,
        ]);
    }

    public function edit(AcademicYear $academicYear): JsonResponse
    {
        $this->authorize('update', $academicYear);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $academicYear,
        ]);
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->academicYears->update($academicYear, $request->validated());

        return redirect()
            ->route('master.academic-years.index')
            ->with('success', 'Tahun ajaran berhasil diperbarui.');
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('delete', $academicYear);

        // ResourceInUseException renders itself as a flash message.
        $this->academicYears->delete($academicYear);

        return redirect()
            ->route('master.academic-years.index')
            ->with('success', 'Tahun ajaran berhasil dihapus.');
    }

    public function activate(AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('activate', $academicYear);

        $this->academicYears->activate($academicYear);

        return back()->with('success', 'Tahun ajaran diaktifkan.');
    }
}
