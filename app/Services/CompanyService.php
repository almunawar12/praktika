<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\TableRequest;
use App\Exceptions\ResourceInUseException;
use App\Models\Company;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyService
{
    public const SORTABLE = ['name', 'business_field', 'quota', 'created_at'];

    /**
     * @return LengthAwarePaginator<int, Company>
     */
    public function paginate(TableRequest $table): LengthAwarePaginator
    {
        return Company::query()
            ->when($table->search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('business_field', 'ilike', "%{$search}%")
                        ->orWhere('pic_name', 'ilike', "%{$search}%");
                });
            })
            ->when($table->filter('is_active'), function ($query, string $value): void {
                $query->where('is_active', $value === 'active');
            })
            ->orderBy($table->sortColumn(self::SORTABLE, 'name'), $table->sortDirection())
            ->paginate($table->perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Company
    {
        return Company::create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Company $company, array $data): Company
    {
        $company->update($data);

        return $company;
    }

    /**
     * @throws ResourceInUseException
     */
    public function delete(Company $company): void
    {
        // placements.company_id is RESTRICT, so without this the delete surfaces as a
        // raw SQL error. Retiring a DUDI is what `is_active` is for.
        if ($company->placements()->exists()) {
            throw new ResourceInUseException(
                'Perusahaan tidak dapat dihapus karena sudah memiliki riwayat penempatan PKL. Nonaktifkan perusahaan jika tidak lagi menerima siswa.'
            );
        }

        $company->delete();
    }
}
