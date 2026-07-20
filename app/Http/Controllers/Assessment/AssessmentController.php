<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assessment;

use App\DataTransferObjects\AssessmentData;
use App\DataTransferObjects\TableRequest;
use App\Enums\AssessmentCriteria;
use App\Enums\AssessmentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Assessment\StoreAssessmentRequest;
use App\Models\Assessment;
use App\Models\Placement;
use App\Services\AssessmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * AssessmentRuleException renders itself as a flash message.
 */
class AssessmentController extends Controller
{
    public function __construct(private readonly AssessmentService $assessments) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Assessment::class);

        $table = TableRequest::fromRequest($request, ['type', 'state']);

        return Inertia::render('assessments/index', [
            'assessments' => $this->assessments->paginate($table, $request->user()),
            'table' => $table->toArray(),
            'types' => array_map(
                fn (AssessmentType $t): array => ['value' => $t->value, 'label' => $t->label()],
                AssessmentType::cases(),
            ),
            'stats' => $this->assessments->stats($request->user()),
        ]);
    }

    /**
     * The form for one side of one placement. Both stages share it — which side is
     * decided by the route, and by which permission the user holds.
     */
    public function edit(Request $request, Placement $placement, string $type): Response
    {
        $assessmentType = AssessmentType::from($type);

        $this->authorize('submitFor', [Assessment::class, $placement, $assessmentType]);

        $placement->load(['student:id,name,nis', 'company:id,name']);
        $assessment = $this->assessments->findOrNew($placement, $assessmentType);

        return Inertia::render('assessments/edit', [
            'placement' => [
                'id' => $placement->id,
                'student' => $placement->student->name,
                'nis' => $placement->student->nis,
                'company' => $placement->company->name,
            ],
            'type' => ['value' => $assessmentType->value, 'label' => $assessmentType->label()],
            'criteria' => array_map(
                fn (AssessmentCriteria $c): array => ['code' => $c->value, 'label' => $c->label()],
                $assessmentType->criteria(),
            ),
            'assessment' => $assessment === null ? null : [
                'id' => $assessment->id,
                'note' => $assessment->note,
                'average_score' => $assessment->average_score,
                'is_submitted' => $assessment->isSubmitted(),
                'scores' => $assessment->scores
                    ->mapWithKeys(fn ($s): array => [$s->criteria_code->value => (float) $s->score])
                    ->all(),
            ],
        ]);
    }

    public function store(
        StoreAssessmentRequest $request,
        Placement $placement,
        string $type,
    ): RedirectResponse {
        $assessmentType = AssessmentType::from($type);

        $this->authorize('submitFor', [Assessment::class, $placement, $assessmentType]);

        $data = AssessmentData::fromRequest($request);

        $this->assessments->save($placement, $assessmentType, $request->user(), $data);

        return redirect()
            ->route('assessments.index')
            ->with('success', $data->submit
                ? 'Penilaian dikirim. Nilai akhir dihitung otomatis setelah kedua penilaian masuk.'
                : 'Draft penilaian disimpan.');
    }

    /**
     * Reopen a submitted assessment — admin only.
     */
    public function unlock(Assessment $assessment): RedirectResponse
    {
        $this->authorize('unlock', $assessment);

        $this->assessments->unlock($assessment);

        return back()->with('success', 'Penilaian dibuka kembali dan dapat diubah penilainya.');
    }
}
