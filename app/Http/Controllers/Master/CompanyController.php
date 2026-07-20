<?php

declare(strict_types=1);

namespace App\Http\Controllers\Master;

use App\DataTransferObjects\TableRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreCompanyRequest;
use App\Http\Requests\Master\UpdateCompanyRequest;
use App\Models\Company;
use App\Services\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function __construct(private readonly CompanyService $companies) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Company::class);

        $table = TableRequest::fromRequest($request, ['is_active']);

        return Inertia::render('master/companies/index', [
            'companies' => $this->companies->paginate($table, $request->user()),
            'table' => $table->toArray(),
            'sortable' => CompanyService::SORTABLE,
            'stats' => $this->companies->stats($request->user()),
        ]);
    }

    public function store(StoreCompanyRequest $request): RedirectResponse
    {
        $this->companies->create($request->validated());

        return redirect()
            ->route('master.companies.index')
            ->with('success', 'Perusahaan berhasil ditambahkan.');
    }

    public function show(Company $company): JsonResponse
    {
        $this->authorize('view', $company);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $company,
        ]);
    }

    public function edit(Company $company): JsonResponse
    {
        $this->authorize('update', $company);

        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $company,
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company): RedirectResponse
    {
        $this->companies->update($company, $request->validated());

        return redirect()
            ->route('master.companies.index')
            ->with('success', 'Perusahaan berhasil diperbarui.');
    }

    public function destroy(Company $company): RedirectResponse
    {
        $this->authorize('delete', $company);

        $this->companies->delete($company);

        return redirect()
            ->route('master.companies.index')
            ->with('success', 'Perusahaan berhasil dihapus.');
    }
}
