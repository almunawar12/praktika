<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\CompanySupervisorData;
use App\DataTransferObjects\TableRequest;
use App\Enums\RoleName;
use App\Models\CompanySupervisor;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CompanySupervisorService
{
    public const SORTABLE = ['name', 'created_at'];

    public function __construct(private readonly UserAccountService $accounts) {}

    /**
     * Totals for the overview cards.
     *
     * @return array{total: int, active: int, inactive: int, companies: int}
     */
    public function stats(): array
    {
        $counts = CompanySupervisor::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where exists (select 1 from users where users.id = company_supervisors.user_id and users.is_active)) as active')
            ->selectRaw('count(distinct company_id) as companies')
            ->first();

        $total = (int) ($counts?->getAttribute('total') ?? 0);
        $active = (int) ($counts?->getAttribute('active') ?? 0);

        return [
            'total' => $total,
            'active' => $active,
            'inactive' => $total - $active,
            'companies' => (int) ($counts?->getAttribute('companies') ?? 0),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, CompanySupervisor>
     */
    public function paginate(TableRequest $table): LengthAwarePaginator
    {
        return CompanySupervisor::query()
            ->with(['user:id,email,is_active', 'company:id,name'])
            ->when($table->search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('position', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q->where('email', 'ilike', "%{$search}%"))
                        ->orWhereHas('company', fn ($q) => $q->where('name', 'ilike', "%{$search}%"));
                });
            })
            ->when($table->filter('company_id'), fn ($query, string $id) => $query->where('company_id', $id))
            ->when($table->filter('is_active'), fn ($query, string $value) => $query
                ->whereHas('user', fn ($q) => $q->where('is_active', $value === 'active')))
            ->orderBy(...$table->sortOrder(self::SORTABLE))
            ->paginate($table->perPage)
            ->withQueryString();
    }

    public function create(CompanySupervisorData $data): CompanySupervisor
    {
        // One transaction: a supervisor row without its account, or an account
        // without its row, is a broken state neither screen can repair.
        return DB::transaction(function () use ($data): CompanySupervisor {
            $user = $this->accounts->createFor(
                RoleName::PembimbingIndustri,
                $data->name,
                $data->email,
                $data->phone,
            );

            return CompanySupervisor::create([
                'user_id' => $user->id,
                'company_id' => $data->companyId,
                'name' => $data->name,
                'position' => $data->position,
                'phone' => $data->phone,
            ]);
        });
    }

    public function update(CompanySupervisor $supervisor, CompanySupervisorData $data): CompanySupervisor
    {
        return DB::transaction(function () use ($supervisor, $data): CompanySupervisor {
            $supervisor->update([
                'company_id' => $data->companyId,
                'name' => $data->name,
                'position' => $data->position,
                'phone' => $data->phone,
            ]);

            $this->accounts->syncFromProfile(
                $supervisor->user,
                $data->name,
                $data->email,
                $data->phone,
            );

            return $supervisor;
        });
    }

    public function delete(CompanySupervisor $supervisor): void
    {
        DB::transaction(function () use ($supervisor): void {
            // Deactivated, not deleted: the account is the causer on verification and
            // review entries that must stay attributable.
            $this->accounts->deactivate($supervisor->user);

            $supervisor->delete();
        });
    }
}
