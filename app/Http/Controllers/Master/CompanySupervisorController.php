<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\DataTransferObjects\CompanySupervisorData;
use App\DataTransferObjects\TableRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreCompanySupervisorRequest;
use App\Http\Requests\Master\UpdateCompanySupervisorRequest;
use App\Models\Company;
use App\Models\CompanySupervisor;
use App\Services\CompanySupervisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySupervisorController extends Controller
{
    public function __construct(private readonly CompanySupervisorService $supervisors) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', CompanySupervisor::class);

        $table = TableRequest::fromRequest($request, ['company_id', 'is_active']);

        return Inertia::render('master/supervisors/index', [
            'supervisors' => $this->supervisors->paginate($table),
            'table' => $table->toArray(),
            'companies' => $this->companyOptions(),
            'stats' => $this->supervisors->stats(),
        ]);
    }

    public function store(StoreCompanySupervisorRequest $request): RedirectResponse
    {
        $this->supervisors->create(CompanySupervisorData::fromRequest($request));

        return redirect()
            ->route('master.supervisors.index')
            ->with('success', 'Pembimbing industri ditambahkan. Akun login dibuat — minta yang bersangkutan melakukan reset kata sandi untuk mengaktifkannya.');
    }

    public function show(CompanySupervisor $supervisor): JsonResponse
    {
        $this->authorize('view', $supervisor);

        $supervisor->load([
            'user:id,email,is_active,last_login_at',
            'company:id,name,business_field',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $supervisor,
        ]);
    }

    public function edit(CompanySupervisor $supervisor): JsonResponse
    {
        $this->authorize('update', $supervisor);

        $supervisor->load([
            'user:id,email',
            'company:id,name,business_field',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $supervisor,
        ]);
    }

    public function update(
        UpdateCompanySupervisorRequest $request,
        CompanySupervisor $supervisor,
    ): RedirectResponse {
        $this->supervisors->update($supervisor, CompanySupervisorData::fromRequest($request));

        return redirect()
            ->route('master.supervisors.index')
            ->with('success', 'Pembimbing industri berhasil diperbarui.');
    }

    public function destroy(CompanySupervisor $supervisor): RedirectResponse
    {
        $this->authorize('delete', $supervisor);

        $this->supervisors->delete($supervisor);

        return redirect()
            ->route('master.supervisors.index')
            ->with('success', 'Pembimbing industri dihapus dan akunnya dinonaktifkan.');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function companyOptions(): array
    {
        return Company::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Company $c): array => ['value' => $c->id, 'label' => $c->name])
            ->all();
    }
}
