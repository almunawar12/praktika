<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Journal\ReviewJournalRequest;
use App\Models\Journal;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;

/**
 * The two review stages sit on separate endpoints with separate permissions
 * (journals.reviewAsIndustry / .reviewAsTeacher), so each decision is its own
 * audit entry and the PRD § 5 order is expressed in the routing itself.
 *
 * JournalRuleException renders itself as a flash message.
 */
class JournalReviewController extends Controller
{
    public function __construct(private readonly JournalService $journals) {}

    public function industry(ReviewJournalRequest $request, Journal $journal): RedirectResponse
    {
        $this->authorize('reviewAsIndustry', $journal);

        $this->journals->reviewAsIndustry(
            $journal,
            $request->user(),
            $request->decision(),
            $request->note(),
        );

        return back()->with('success', $request->decision() === ReviewStatus::Approved
            ? 'Jurnal disetujui. Diteruskan ke guru pembimbing.'
            : 'Jurnal ditolak. Siswa dapat memperbaikinya.');
    }

    public function teacher(ReviewJournalRequest $request, Journal $journal): RedirectResponse
    {
        $this->authorize('reviewAsTeacher', $journal);

        $this->journals->reviewAsTeacher(
            $journal,
            $request->user(),
            $request->decision(),
            $request->note(),
        );

        return back()->with('success', $request->decision() === ReviewStatus::Approved
            ? 'Jurnal disetujui.'
            : 'Jurnal ditolak. Siswa dapat memperbaikinya.');
    }
}
