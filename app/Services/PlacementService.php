<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\PlacementData;
use App\DataTransferObjects\TableRequest;
use App\Enums\PlacementStatus;
use App\Exceptions\PlacementRuleException;
use App\Models\Company;
use App\Models\Placement;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class PlacementService
{
    public const SORTABLE = ['start_date', 'end_date', 'status', 'created_at'];

    /**
     * Totals for the overview cards, scoped to what the viewer may see. Enum values
     * are code constants, so interpolating them into the aggregate is injection-safe.
     *
     * @return array{total: int, active: int, completed: int, draft: int}
     */
    public function stats(User $viewer): array
    {
        $counts = Placement::query()
            ->visibleTo($viewer)
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where status = '".PlacementStatus::Active->value."') as active")
            ->selectRaw("count(*) filter (where status = '".PlacementStatus::Completed->value."') as completed")
            ->selectRaw("count(*) filter (where status = '".PlacementStatus::Draft->value."') as draft")
            ->first();

        return [
            'total' => (int) ($counts?->getAttribute('total') ?? 0),
            'active' => (int) ($counts?->getAttribute('active') ?? 0),
            'completed' => (int) ($counts?->getAttribute('completed') ?? 0),
            'draft' => (int) ($counts?->getAttribute('draft') ?? 0),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Placement>
     */
    public function paginate(TableRequest $table, User $viewer): LengthAwarePaginator
    {
        return Placement::query()
            ->visibleTo($viewer)
            ->with([
                'student:id,name,nis,class_id',
                'student.schoolClass:id,name,department_id',
                'student.schoolClass.department:id,code',
                'company:id,name',
                'teacher:id,name',
            ])
            ->when($table->search, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->whereHas('student', fn ($q) => $q
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('nis', 'ilike', "%{$search}%"))
                        ->orWhereHas('company', fn ($q) => $q->where('name', 'ilike', "%{$search}%"))
                        ->orWhereHas('teacher', fn ($q) => $q->where('name', 'ilike', "%{$search}%"));
                });
            })
            ->when($table->filter('status'), fn ($query, string $status) => $query->where('status', $status))
            ->when($table->filter('company_id'), fn ($query, string $id) => $query->where('company_id', $id))
            ->when($table->filter('teacher_id'), fn ($query, string $id) => $query->where('teacher_id', $id))
            ->orderBy($table->sortColumn(self::SORTABLE, 'start_date'), $table->sortDirection())
            ->paginate($table->perPage)
            ->withQueryString();
    }

    /**
     * Assign a student to a company under a supervising teacher.
     *
     * New placements always start as `draft`: nothing is committed against the
     * company's quota or the student's single active slot until someone activates it.
     */
    public function create(PlacementData $data): Placement
    {
        return DB::transaction(fn (): Placement => Placement::create([
            ...$data->toAttributes(),
            'status' => PlacementStatus::Draft,
        ]));
    }

    /**
     * @throws PlacementRuleException
     */
    public function update(Placement $placement, PlacementData $data): Placement
    {
        if (! $placement->status->isEditable()) {
            throw PlacementRuleException::notEditable($placement->status);
        }

        return DB::transaction(function () use ($placement, $data): Placement {
            // An active placement already holds a quota slot and the student's active
            // slot. Moving it to a different student or company must re-check both,
            // or the edit form becomes a way around activate()'s rules.
            if ($placement->status->occupiesSlot()) {
                if ($data->studentId !== $placement->student_id) {
                    $this->assertStudentHasNoActivePlacement(
                        Student::findOrFail($data->studentId),
                        exceptPlacementId: $placement->id,
                    );
                }

                if ($data->companyId !== $placement->company_id) {
                    $this->assertCompanyHasCapacity(
                        Company::findOrFail($data->companyId),
                        exceptPlacementId: $placement->id,
                    );
                }
            }

            $placement->update($data->toAttributes());

            return $placement;
        });
    }

    /**
     * @throws PlacementRuleException
     */
    public function activate(Placement $placement): Placement
    {
        return $this->transition($placement, PlacementStatus::Active, function (Placement $placement): void {
            $company = $placement->company;

            if (! $company->is_active) {
                throw PlacementRuleException::companyInactive($company);
            }

            $this->assertStudentHasNoActivePlacement($placement->student, exceptPlacementId: $placement->id);
            $this->assertCompanyHasCapacity($company, exceptPlacementId: $placement->id);
        });
    }

    /**
     * @throws PlacementRuleException
     */
    public function complete(Placement $placement): Placement
    {
        return $this->transition($placement, PlacementStatus::Completed);
    }

    /**
     * @throws PlacementRuleException
     */
    public function cancel(Placement $placement, ?string $reason): Placement
    {
        return $this->transition(
            $placement,
            PlacementStatus::Cancelled,
            after: function (Placement $placement) use ($reason): void {
                $placement->cancel_reason = $reason;
            },
        );
    }

    /**
     * @throws PlacementRuleException
     */
    public function delete(Placement $placement): void
    {
        // Cancelling is the way to retire a placement; deleting is only for a draft
        // created by mistake. Once a PKL has run, its row is the parent of attendance
        // and journal records that must stay reachable.
        if ($placement->status !== PlacementStatus::Draft) {
            throw new PlacementRuleException(
                'Hanya penempatan berstatus Draft yang dapat dihapus. Gunakan Batalkan untuk penempatan lainnya.'
            );
        }

        $placement->delete();
    }

    /**
     * Remaining slots at a company, counting only placements that hold one.
     */
    public function remainingQuota(Company $company): int
    {
        return max(0, $company->quota - $this->activeCountFor($company));
    }

    /**
     * @param  (callable(Placement): void)|null  $guard
     * @param  (callable(Placement): void)|null  $after
     *
     * @throws PlacementRuleException
     */
    private function transition(
        Placement $placement,
        PlacementStatus $target,
        ?callable $guard = null,
        ?callable $after = null,
    ): Placement {
        if (! $placement->status->canTransitionTo($target)) {
            throw PlacementRuleException::invalidTransition($placement->status, $target);
        }

        return DB::transaction(function () use ($placement, $target, $guard, $after): Placement {
            if ($guard !== null) {
                $guard($placement);
            }

            $placement->status = $target;

            if ($after !== null) {
                $after($placement);
            }

            try {
                $placement->save();
            } catch (QueryException $exception) {
                // Two admins activating placements for the same student at the same
                // moment both pass the check above; the partial unique index is what
                // actually stops the second one.
                if ($this->isOneActiveViolation($exception)) {
                    throw PlacementRuleException::studentAlreadyActive($placement->student);
                }

                throw $exception;
            }

            return $placement;
        });
    }

    /**
     * @throws PlacementRuleException
     */
    private function assertStudentHasNoActivePlacement(Student $student, ?string $exceptPlacementId = null): void
    {
        $exists = Placement::query()
            ->active()
            ->where('student_id', $student->id)
            ->when($exceptPlacementId, fn ($query, string $id) => $query->whereKeyNot($id))
            ->exists();

        if ($exists) {
            throw PlacementRuleException::studentAlreadyActive($student);
        }
    }

    /**
     * @throws PlacementRuleException
     */
    private function assertCompanyHasCapacity(Company $company, ?string $exceptPlacementId = null): void
    {
        $used = $this->activeCountFor($company, $exceptPlacementId);

        if ($used >= $company->quota) {
            throw PlacementRuleException::quotaExceeded($company, $used);
        }
    }

    private function activeCountFor(Company $company, ?string $exceptPlacementId = null): int
    {
        return Placement::query()
            ->active()
            ->where('company_id', $company->id)
            ->when($exceptPlacementId, fn ($query, string $id) => $query->whereKeyNot($id))
            ->count();
    }

    private function isOneActiveViolation(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'placements_one_active_per_student');
    }
}
