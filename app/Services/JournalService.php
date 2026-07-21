<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\JournalData;
use App\DataTransferObjects\TableRequest;
use App\Enums\ReviewStatus;
use App\Exceptions\JournalRuleException;
use App\Models\Journal;
use App\Models\Placement;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class JournalService
{
    public const SORTABLE = ['date', 'status', 'industry_status', 'teacher_status', 'created_at'];

    /**
     * Totals for the overview cards, scoped to what the viewer may see.
     *
     * @return array{total: int, pending: int, approved: int, rejected: int}
     */
    public function stats(User $viewer): array
    {
        $counts = Journal::query()
            ->visibleTo($viewer)
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where status = '".ReviewStatus::Pending->value."') as pending")
            ->selectRaw("count(*) filter (where status = '".ReviewStatus::Approved->value."') as approved")
            ->selectRaw("count(*) filter (where status = '".ReviewStatus::Rejected->value."') as rejected")
            ->first();

        return [
            'total' => (int) ($counts?->getAttribute('total') ?? 0),
            'pending' => (int) ($counts?->getAttribute('pending') ?? 0),
            'approved' => (int) ($counts?->getAttribute('approved') ?? 0),
            'rejected' => (int) ($counts?->getAttribute('rejected') ?? 0),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Journal>
     */
    public function paginate(TableRequest $table, User $viewer): LengthAwarePaginator
    {
        return Journal::query()
            ->visibleTo($viewer)
            ->with([
                'placement:id,student_id,company_id,teacher_id',
                'placement.student:id,name,nis',
                'placement.company:id,name',
            ])
            ->withCount('attachments')
            ->when($table->search, fn ($query, string $search) => $query
                ->where(function ($query) use ($search): void {
                    $query->where('activity', 'ilike', "%{$search}%")
                        ->orWhereHas('placement.student', fn ($q) => $q
                            ->where('name', 'ilike', "%{$search}%")
                            ->orWhere('nis', 'ilike', "%{$search}%"));
                }))
            ->when($table->filter('status'), fn ($query, string $s) => $query->where('status', $s))
            ->when($table->filter('industry_status'), fn ($query, string $s) => $query
                ->where('industry_status', $s))
            ->when($table->filter('teacher_status'), fn ($query, string $s) => $query
                ->where('teacher_status', $s))
            // "Needs my review" is the query both reviewers actually want.
            ->when($table->filter('queue') === 'industry', fn ($query) => $query->pendingForIndustry())
            ->when($table->filter('queue') === 'teacher', fn ($query) => $query->pendingForTeacher())
            ->orderBy(...$table->sortOrder(self::SORTABLE))
            ->paginate($table->perPage)
            ->withQueryString();
    }

    /**
     * @throws JournalRuleException
     */
    public function activePlacementFor(Student $student): Placement
    {
        $placement = $student->activePlacement()->first();

        if ($placement === null) {
            throw JournalRuleException::noActivePlacement();
        }

        return $placement;
    }

    /**
     * @throws JournalRuleException
     */
    public function create(Student $student, string $date, JournalData $data): Journal
    {
        $placement = $this->activePlacementFor($student);
        $date = CarbonImmutable::parse($date)->startOfDay();

        $this->assertDateAllowed($placement, $date);

        try {
            return Journal::create([
                'placement_id' => $placement->id,
                'date' => $date->toDateString(),
                ...$data->toContent(),
                'status' => ReviewStatus::Pending,
                'industry_status' => ReviewStatus::Pending,
                'teacher_status' => ReviewStatus::Pending,
            ]);
        } catch (QueryException $exception) {
            // Two submits both pass the existence check; the unique index is what
            // actually stops the second.
            if ($this->isDuplicateDay($exception)) {
                throw JournalRuleException::alreadyExists();
            }

            throw $exception;
        }
    }

    /**
     * Save a revision.
     *
     * Editing resets both stages: the content a reviewer approved is no longer the
     * content on the page, so their decision can not be carried over to text they
     * never saw. The old notes stay as the feedback the student is acting on.
     *
     * @throws JournalRuleException
     */
    public function update(Journal $journal, JournalData $data): Journal
    {
        if (! $journal->isEditable()) {
            throw JournalRuleException::locked();
        }

        return DB::transaction(function () use ($journal, $data): Journal {
            $journal->update([
                ...$data->toContent(),
                'industry_status' => ReviewStatus::Pending,
                'teacher_status' => ReviewStatus::Pending,
                'status' => ReviewStatus::Pending,
            ]);

            return $journal;
        });
    }

    public function reviewAsIndustry(Journal $journal, User $reviewer, ReviewStatus $decision, ?string $note): Journal
    {
        return DB::transaction(function () use ($journal, $reviewer, $decision, $note): Journal {
            // No need to clear a teacher decision here: industry only reviews while
            // its own status is pending, and the journals_teacher_requires_industry
            // CHECK guarantees teacher_status is pending whenever that is true.
            $journal->industry_status = $decision;
            $journal->industry_reviewed_by = $reviewer->id;
            $journal->industry_reviewed_at = CarbonImmutable::now();
            $journal->industry_note = $note;

            $journal->status = $this->resolveFinalStatus($journal);
            $journal->save();

            return $journal;
        });
    }

    /**
     * @throws JournalRuleException
     */
    public function reviewAsTeacher(Journal $journal, User $reviewer, ReviewStatus $decision, ?string $note): Journal
    {
        // PRD § 5 order: the company signs off first.
        if ($journal->industry_status !== ReviewStatus::Approved) {
            throw JournalRuleException::industryReviewPending();
        }

        return DB::transaction(function () use ($journal, $reviewer, $decision, $note): Journal {
            $journal->teacher_status = $decision;
            $journal->teacher_reviewed_by = $reviewer->id;
            $journal->teacher_reviewed_at = CarbonImmutable::now();
            $journal->teacher_note = $note;
            $journal->status = $this->resolveFinalStatus($journal);
            $journal->save();

            return $journal;
        });
    }

    /**
     * The single definition of a journal's overall status.
     */
    public function resolveFinalStatus(Journal $journal): ReviewStatus
    {
        if ($journal->industry_status === ReviewStatus::Rejected
            || $journal->teacher_status === ReviewStatus::Rejected) {
            return ReviewStatus::Rejected;
        }

        if ($journal->industry_status === ReviewStatus::Approved
            && $journal->teacher_status === ReviewStatus::Approved) {
            return ReviewStatus::Approved;
        }

        return ReviewStatus::Pending;
    }

    /**
     * Share of working days in the placement so far that carry a journal.
     */
    public function completionRate(Placement $placement): float
    {
        $end = CarbonImmutable::now()->startOfDay()->min($placement->end_date);

        if ($end->lt($placement->start_date)) {
            return 0.0;
        }

        $workingDays = 0;

        for ($date = $placement->start_date; $date->lte($end); $date = $date->addDay()) {
            if (! $date->isWeekend()) {
                $workingDays++;
            }
        }

        if ($workingDays === 0) {
            return 0.0;
        }

        $filled = Journal::query()->where('placement_id', $placement->id)->count();

        return round(min(100, $filled / $workingDays * 100), 1);
    }

    /**
     * @throws JournalRuleException
     */
    private function assertDateAllowed(Placement $placement, CarbonImmutable $date): void
    {
        // A journal describes what happened; there is nothing to describe yet.
        if ($date->gt(CarbonImmutable::now()->startOfDay())) {
            throw JournalRuleException::futureDate();
        }

        if ($date->lt($placement->start_date) || $date->gt($placement->end_date)) {
            throw JournalRuleException::outsidePlacementPeriod();
        }

        $exists = Journal::query()
            ->where('placement_id', $placement->id)
            ->whereDate('date', $date->toDateString())
            ->exists();

        if ($exists) {
            throw JournalRuleException::alreadyExists();
        }
    }

    private function isDuplicateDay(QueryException $exception): bool
    {
        return str_contains($exception->getMessage(), 'journals_placement_id_date_unique');
    }
}
