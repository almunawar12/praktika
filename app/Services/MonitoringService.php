<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\MonitoringData;
use App\DataTransferObjects\TableRequest;
use App\Enums\PlacementStatus;
use App\Exceptions\MonitoringRuleException;
use App\Models\Monitoring;
use App\Models\Placement;
use App\Models\Teacher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class MonitoringService
{
    public const SORTABLE = ['visit_date', 'created_at'];

    /**
     * Statuses a visit may be filed against.
     *
     * `completed` is included deliberately: a teacher who forgot to file before the
     * PKL ended must still be able to, and the final report is exactly when that is
     * noticed. Draft and cancelled are refused — there was no PKL to visit.
     *
     * @return array<int, PlacementStatus>
     */
    public const VISITABLE = [PlacementStatus::Active, PlacementStatus::Completed];

    /**
     * Totals for the overview cards, scoped to what the viewer may see. Monitoring
     * has no status of its own, so the cards report coverage instead.
     *
     * @return array{total: int, thisMonth: int, withLocation: int, withEvidence: int}
     */
    public function stats(User $viewer): array
    {
        $counts = Monitoring::query()
            ->visibleTo($viewer)
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where date_trunc('month', visit_date) = date_trunc('month', current_date)) as this_month")
            ->selectRaw('count(*) filter (where latitude is not null) as with_location')
            ->first();

        // Counted through the relation so the polymorphic type resolves correctly.
        $withEvidence = Monitoring::query()
            ->visibleTo($viewer)
            ->whereHas('attachments')
            ->count();

        return [
            'total' => (int) ($counts?->getAttribute('total') ?? 0),
            'thisMonth' => (int) ($counts?->getAttribute('this_month') ?? 0),
            'withLocation' => (int) ($counts?->getAttribute('with_location') ?? 0),
            'withEvidence' => $withEvidence,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Monitoring>
     */
    public function paginate(TableRequest $table, User $viewer): LengthAwarePaginator
    {
        return Monitoring::query()
            ->visibleTo($viewer)
            ->with([
                'placement:id,student_id,company_id,teacher_id',
                'placement.student:id,name,nis',
                'placement.company:id,name',
                'teacher:id,name',
            ])
            ->withCount('attachments')
            ->when($table->search, fn ($query, string $search) => $query
                ->where(function ($query) use ($search): void {
                    $query->where('note', 'ilike', "%{$search}%")
                        ->orWhere('recommendation', 'ilike', "%{$search}%")
                        ->orWhereHas('placement.student', fn ($q) => $q
                            ->where('name', 'ilike', "%{$search}%")
                            ->orWhere('nis', 'ilike', "%{$search}%"))
                        ->orWhereHas('placement.company', fn ($q) => $q
                            ->where('name', 'ilike', "%{$search}%"));
                }))
            ->when($table->filter('placement_id'), fn ($query, string $id) => $query
                ->where('placement_id', $id))
            ->when($table->filter('teacher_id'), fn ($query, string $id) => $query
                ->where('teacher_id', $id))
            ->orderBy($table->sortColumn(self::SORTABLE, 'visit_date'), $table->sortDirection())
            ->paginate($table->perPage)
            ->withQueryString();
    }

    /**
     * @throws MonitoringRuleException
     */
    public function record(Placement $placement, Teacher $teacher, MonitoringData $data): Monitoring
    {
        $this->assertVisitable($placement, $data->visitDate);

        return Monitoring::create([
            'placement_id' => $placement->id,
            // Denormalised: the report keeps naming whoever actually made the visit,
            // even if the placement is later reassigned to another teacher.
            'teacher_id' => $teacher->id,
            ...$data->toAttributes(),
        ]);
    }

    /**
     * @throws MonitoringRuleException
     */
    public function update(Monitoring $monitoring, MonitoringData $data): Monitoring
    {
        $this->assertVisitable($monitoring->placement, $data->visitDate);

        $monitoring->update($data->toAttributes());

        return $monitoring;
    }

    public function delete(Monitoring $monitoring): void
    {
        $monitoring->delete();
    }

    /**
     * @return Collection<int, Monitoring>
     */
    public function historyFor(Placement $placement): Collection
    {
        return Monitoring::query()
            ->where('placement_id', $placement->id)
            ->with(['teacher:id,name', 'attachments'])
            ->orderByDesc('visit_date')
            ->get();
    }

    /**
     * Placements this teacher supervises that have had no visit for a while.
     *
     * @return Collection<int, Placement>
     */
    public function needingVisit(Teacher $teacher, int $sinceDays = 30): Collection
    {
        $cutoff = CarbonImmutable::now()->subDays($sinceDays);

        return Placement::query()
            ->active()
            ->where('teacher_id', $teacher->id)
            ->whereDoesntHave('monitorings', fn ($query) => $query->where('visit_date', '>=', $cutoff))
            ->with(['student:id,name', 'company:id,name'])
            ->get();
    }

    /**
     * @throws MonitoringRuleException
     */
    private function assertVisitable(Placement $placement, string $visitDate): void
    {
        if (! in_array($placement->status, self::VISITABLE, true)) {
            throw MonitoringRuleException::placementNotVisitable($placement->status);
        }

        $date = CarbonImmutable::parse($visitDate)->startOfDay();

        if ($date->gt(CarbonImmutable::now()->startOfDay())) {
            throw MonitoringRuleException::futureDate();
        }

        // A visit outside the PKL period did not happen during the PKL.
        if ($date->lt($placement->start_date) || $date->gt($placement->end_date)) {
            throw MonitoringRuleException::outsidePlacementPeriod();
        }
    }
}
