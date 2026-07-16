<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\AssessmentData;
use App\DataTransferObjects\TableRequest;
use App\Enums\AssessmentType;
use App\Enums\PlacementStatus;
use App\Exceptions\AssessmentRuleException;
use App\Models\Assessment;
use App\Models\Placement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AssessmentService
{
    public const SORTABLE = ['type', 'average_score', 'submitted_at', 'created_at'];

    /**
     * A PKL can only be assessed once it is under way — and still after it ends,
     * since that is usually when assessment actually happens.
     *
     * @return array<int, PlacementStatus>
     */
    public const ASSESSABLE = [PlacementStatus::Active, PlacementStatus::Completed];

    /**
     * @return LengthAwarePaginator<int, Assessment>
     */
    public function paginate(TableRequest $table, User $viewer): LengthAwarePaginator
    {
        return Assessment::query()
            ->visibleTo($viewer)
            ->with([
                'placement:id,student_id,company_id,teacher_id',
                'placement.student:id,name,nis',
                'placement.company:id,name',
                'assessor:id,name',
            ])
            ->when($table->search, fn ($query, string $search) => $query
                ->whereHas('placement.student', fn ($q) => $q
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%")))
            ->when($table->filter('type'), fn ($query, string $type) => $query->where('type', $type))
            ->when($table->filter('state') === 'draft', fn ($query) => $query->whereNull('submitted_at'))
            ->when($table->filter('state') === 'submitted', fn ($query) => $query->submitted())
            ->orderBy($table->sortColumn(self::SORTABLE, 'created_at'), $table->sortDirection())
            ->paginate($table->perPage)
            ->withQueryString();
    }

    /**
     * Find the assessment this assessor is working on, or start a draft.
     */
    public function findOrNew(Placement $placement, AssessmentType $type): ?Assessment
    {
        return Assessment::query()
            ->where('placement_id', $placement->id)
            ->where('type', $type)
            ->with('scores')
            ->first();
    }

    /**
     * Save a draft or submit it — the same write, differing only in `submitted_at`.
     *
     * @throws AssessmentRuleException
     */
    public function save(
        Placement $placement,
        AssessmentType $type,
        User $assessor,
        AssessmentData $data,
    ): Assessment {
        if (! in_array($placement->status, self::ASSESSABLE, true)) {
            throw AssessmentRuleException::placementNotAssessable();
        }

        $existing = $this->findOrNew($placement, $type);

        // Once submitted it is evidence the final score rests on; only an admin may
        // reopen it.
        if ($existing?->isSubmitted()) {
            throw AssessmentRuleException::alreadySubmitted($type);
        }

        return DB::transaction(function () use ($placement, $type, $assessor, $data, $existing): Assessment {
            $assessment = $existing ?? new Assessment([
                'placement_id' => $placement->id,
                'type' => $type,
            ]);

            $assessment->fill([
                // Whoever last touched it owns it — a reassigned supervisor should not
                // be credited with someone else's judgement.
                'assessor_id' => $assessor->id,
                'average_score' => $data->average(),
                'note' => $data->note,
                'submitted_at' => $data->submit ? CarbonImmutable::now() : null,
            ]);

            $assessment->save();

            foreach ($data->scores as $code => $score) {
                $assessment->scores()->updateOrCreate(
                    ['criteria_code' => $code],
                    ['score' => $score],
                );
            }

            return $assessment;
        });
    }

    /**
     * Reopen a submitted assessment. Admin only — see AssessmentPolicy::unlock.
     */
    public function unlock(Assessment $assessment): Assessment
    {
        $assessment->update(['submitted_at' => null]);

        return $assessment;
    }
}
