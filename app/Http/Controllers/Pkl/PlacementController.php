<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pkl;

use App\DataTransferObjects\PlacementData;
use App\DataTransferObjects\TableRequest;
use App\Enums\PlacementStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pkl\StorePlacementRequest;
use App\Http\Requests\Pkl\UpdatePlacementRequest;
use App\Models\Company;
use App\Models\Placement;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\PlacementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PlacementController extends Controller
{
    public function __construct(private readonly PlacementService $placements) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Placement::class);

        $table = TableRequest::fromRequest($request, ['status', 'company_id', 'teacher_id']);

        return Inertia::render('pkl/placements/index', [
            'placements' => $this->placements->paginate($table, $request->user()),
            'table' => $table->toArray(),
            'statuses' => $this->statusOptions(),
            'companies' => $this->companyOptions(),
            'teachers' => $this->teacherOptions(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Placement::class);

        return Inertia::render('pkl/placements/create', [
            'students' => $this->studentOptions(),
            'companies' => $this->companyOptions(withQuota: true),
            'teachers' => $this->teacherOptions(),
        ]);
    }

    public function store(StorePlacementRequest $request): RedirectResponse
    {
        $placement = $this->placements->create(PlacementData::fromRequest($request));

        return redirect()
            ->route('pkl.placements.show', $placement)
            ->with('success', 'Penempatan dibuat sebagai draft. Aktifkan untuk memulai PKL.');
    }

    public function show(Request $request, Placement $placement): Response
    {
        $this->authorize('view', $placement);

        $user = $request->user();

        $placement->load([
            'student:id,name,nis,nisn,class_id,user_id',
            'student.schoolClass:id,name,department_id',
            'student.schoolClass.department:id,code,name',
            'company:id,name,business_field,address,pic_name,pic_phone,quota,is_active',
            'teacher:id,name,nip,user_id',
            'teacher.user:id,email',
        ]);

        return Inertia::render('pkl/placements/show', [
            'placement' => $placement,
            'remainingQuota' => $this->placements->remainingQuota($placement->company),
            // Resolved server-side: which transitions are offered depends on the
            // placement's current status, not only on the user's permissions, so the
            // frontend can not work this out from the shared permission list alone.
            'can' => [
                'update' => $user->can('update', $placement),
                'delete' => $user->can('delete', $placement),
                'activate' => $user->can('activate', $placement),
                'complete' => $user->can('complete', $placement),
                'cancel' => $user->can('cancel', $placement),
            ],
        ]);
    }

    public function edit(Placement $placement): Response
    {
        $this->authorize('update', $placement);

        return Inertia::render('pkl/placements/edit', [
            'placement' => $placement,
            'students' => $this->studentOptions(),
            'companies' => $this->companyOptions(withQuota: true),
            'teachers' => $this->teacherOptions(),
        ]);
    }

    public function update(UpdatePlacementRequest $request, Placement $placement): RedirectResponse
    {
        $this->placements->update($placement, PlacementData::fromRequest($request));

        return redirect()
            ->route('pkl.placements.show', $placement)
            ->with('success', 'Penempatan berhasil diperbarui.');
    }

    public function destroy(Placement $placement): RedirectResponse
    {
        $this->authorize('delete', $placement);

        $this->placements->delete($placement);

        return redirect()
            ->route('pkl.placements.index')
            ->with('success', 'Draft penempatan dihapus.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (PlacementStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            PlacementStatus::cases(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function studentOptions(): array
    {
        return Student::query()
            ->with('schoolClass:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'nis', 'class_id'])
            ->map(fn (Student $s): array => [
                'value' => $s->id,
                'label' => "{$s->name} — {$s->nis} ({$s->schoolClass->name})",
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function companyOptions(bool $withQuota = false): array
    {
        $companies = Company::query()->orderBy('name')->get(['id', 'name', 'quota', 'is_active']);

        return $companies
            // An inactive company can not be activated against, so offering it in the
            // picker would only set the admin up to fail at activation.
            ->when($withQuota, fn ($rows) => $rows->where('is_active', true))
            ->map(function (Company $company) use ($withQuota): array {
                if (! $withQuota) {
                    return ['value' => $company->id, 'label' => $company->name];
                }

                $remaining = $this->placements->remainingQuota($company);

                return [
                    'value' => $company->id,
                    'label' => "{$company->name} — sisa kuota {$remaining}/{$company->quota}",
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function teacherOptions(): array
    {
        return Teacher::query()
            ->orderBy('name')
            ->get(['id', 'name', 'nip'])
            ->map(fn (Teacher $t): array => [
                'value' => $t->id,
                'label' => "{$t->name} — {$t->nip}",
            ])
            ->all();
    }
}
