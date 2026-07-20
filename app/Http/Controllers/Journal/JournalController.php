<?php

declare(strict_types=1);

namespace App\Http\Controllers\Journal;

use App\DataTransferObjects\JournalData;
use App\DataTransferObjects\TableRequest;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Journal\StoreJournalRequest;
use App\Http\Requests\Journal\UpdateJournalRequest;
use App\Models\Journal;
use App\Services\AttachmentService;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class JournalController extends Controller
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly AttachmentService $attachments,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Journal::class);

        $table = TableRequest::fromRequest($request, [
            'status', 'industry_status', 'teacher_status', 'queue',
        ]);

        return Inertia::render('journals/index', [
            'journals' => $this->journals->paginate($table, $request->user()),
            'table' => $table->toArray(),
            'statuses' => $this->statusOptions(),
            'can' => [
                'create' => $request->user()->can('create', Journal::class),
                'reviewAsIndustry' => $request->user()->can('journals.reviewAsIndustry'),
                'reviewAsTeacher' => $request->user()->can('journals.reviewAsTeacher'),
            ],
            'stats' => $this->journals->stats($request->user()),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Journal::class);

        $placement = $request->user()->student?->activePlacement()->with('company')->first();

        return Inertia::render('journals/create', [
            'placement' => $placement === null ? null : [
                'company' => $placement->company->name,
                'start_date' => $placement->start_date->toDateString(),
                'end_date' => $placement->end_date->toDateString(),
            ],
        ]);
    }

    public function store(StoreJournalRequest $request): RedirectResponse
    {
        // One transaction: a journal whose documentation half-uploaded is a state
        // the student can not see or repair.
        $journal = DB::transaction(function () use ($request): Journal {
            $journal = $this->journals->create(
                $request->user()->student,
                (string) $request->string('date'),
                JournalData::fromRequest($request),
            );

            $this->storeAttachments($request, $journal);

            return $journal;
        });

        return redirect()
            ->route('journals.show', $journal)
            ->with('success', 'Jurnal tersimpan. Menunggu review pembimbing industri.');
    }

    public function show(Request $request, Journal $journal): Response
    {
        $this->authorize('view', $journal);

        $journal->load([
            'placement:id,student_id,company_id,teacher_id',
            'placement.student:id,name,nis',
            'placement.company:id,name',
            'industryReviewer:id,name',
            'teacherReviewer:id,name',
            'attachments',
        ]);

        $user = $request->user();

        return Inertia::render('journals/show', [
            'journal' => [
                ...$journal->toArray(),
                'is_editable' => $journal->isEditable(),
                'attachments' => $journal->attachments->map(fn ($a): array => [
                    'id' => $a->id,
                    'original_name' => $a->original_name,
                    'caption' => $a->caption,
                    'size' => $a->size,
                    'is_image' => $a->isImage(),
                    'url' => route('attachments.show', $a),
                ])->all(),
            ],
            // Resolved server-side: which actions are offered depends on the review
            // stage, not only on the user's permissions.
            'can' => [
                'update' => $user->can('update', $journal),
                'delete' => $user->can('delete', $journal),
                'reviewAsIndustry' => $user->can('reviewAsIndustry', $journal),
                'reviewAsTeacher' => $user->can('reviewAsTeacher', $journal),
            ],
        ]);
    }

    public function edit(Journal $journal): Response
    {
        $this->authorize('update', $journal);

        $journal->load('attachments');

        return Inertia::render('journals/edit', [
            'journal' => [
                ...$journal->toArray(),
                'attachments' => $journal->attachments->map(fn ($a): array => [
                    'id' => $a->id,
                    'original_name' => $a->original_name,
                    'caption' => $a->caption,
                    'is_image' => $a->isImage(),
                    'url' => route('attachments.show', $a),
                ])->all(),
            ],
        ]);
    }

    public function update(UpdateJournalRequest $request, Journal $journal): RedirectResponse
    {
        DB::transaction(function () use ($request, $journal): void {
            $this->journals->update($journal, JournalData::fromRequest($request));

            $this->storeAttachments($request, $journal);
        });

        return redirect()
            ->route('journals.show', $journal)
            ->with('success', 'Jurnal diperbarui dan dikirim ulang untuk direview.');
    }

    public function destroy(Journal $journal): RedirectResponse
    {
        $this->authorize('delete', $journal);

        $journal->delete();

        return redirect()
            ->route('journals.index')
            ->with('success', 'Jurnal dihapus.');
    }

    private function storeAttachments(Request $request, Journal $journal): void
    {
        /** @var array<int, UploadedFile> $files */
        $files = $request->file('attachments', []);

        foreach ($files as $file) {
            $this->attachments->store($journal, $file, $request->user());
        }
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (ReviewStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
            ReviewStatus::cases(),
        );
    }
}
