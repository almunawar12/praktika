<?php

declare(strict_types=1);

namespace App\Http\Controllers\Assessment;

use App\DataTransferObjects\TableRequest;
use App\Http\Controllers\Controller;
use App\Models\FinalScore;
use App\Models\Placement;
use App\Services\ScoreCalculatorService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinalScoreController extends Controller
{
    public function __construct(private readonly ScoreCalculatorService $calculator) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FinalScore::class);

        $table = TableRequest::fromRequest($request, ['grade', 'state']);
        $user = $request->user();

        $scores = FinalScore::query()
            ->visibleTo($user)
            ->with([
                'placement:id,student_id,company_id,teacher_id',
                'placement.student:id,name,nis',
                'placement.company:id,name',
                'publisher:id,name',
            ])
            ->when($table->search, fn ($query, string $search) => $query
                ->whereHas('placement.student', fn ($q) => $q
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('nis', 'ilike', "%{$search}%")))
            ->when($table->filter('grade'), fn ($query, string $g) => $query->where('grade', $g))
            ->when($table->filter('state') === 'published', fn ($query) => $query->published())
            ->when($table->filter('state') === 'draft', fn ($query) => $query->whereNull('published_at'))
            ->orderBy(...$table->sortOrder(['final_score', 'grade', 'created_at']))
            ->paginate($table->perPage)
            ->withQueryString();

        $counts = FinalScore::query()
            ->visibleTo($user)
            ->selectRaw('count(*) as total')
            ->selectRaw('count(*) filter (where published_at is not null) as published')
            ->selectRaw('avg(final_score) as average')
            ->first();

        $total = (int) ($counts?->getAttribute('total') ?? 0);
        $published = (int) ($counts?->getAttribute('published') ?? 0);
        $average = $counts?->getAttribute('average');

        return Inertia::render('final-scores/index', [
            'scores' => $scores,
            'table' => $table->toArray(),
            'can' => [
                'publish' => $user->can('final-scores.publish'),
                'calculate' => $user->can('final-scores.calculate'),
            ],
            'stats' => [
                'total' => $total,
                'published' => $published,
                'draft' => $total - $published,
                'average' => $average === null ? null : round((float) $average, 2),
            ],
        ]);
    }

    public function show(Request $request, FinalScore $finalScore): Response
    {
        $this->authorize('view', $finalScore);

        $finalScore->load([
            'placement:id,student_id,company_id,teacher_id,start_date,end_date',
            'placement.student:id,name,nis',
            'placement.company:id,name',
            'placement.teacher:id,name',
            'publisher:id,name',
        ]);

        $user = $request->user();

        return Inertia::render('final-scores/show', [
            'score' => [
                ...$finalScore->toArray(),
                'is_published' => $finalScore->isPublished(),
            ],
            'can' => [
                'publish' => $user->can('publish', $finalScore),
                'unpublish' => $user->can('unpublish', $finalScore),
                'calculate' => $user->can('calculate', [FinalScore::class, $finalScore->placement]),
            ],
        ]);
    }

    /**
     * Recalculate — for when the weights changed after the score was first computed.
     * Scores are otherwise computed automatically by AssessmentObserver.
     */
    public function calculate(Placement $placement): RedirectResponse
    {
        $this->authorize('calculate', [FinalScore::class, $placement]);

        $score = $this->calculator->calculate($placement);

        return redirect()
            ->route('final-scores.show', $score)
            ->with('success', 'Nilai akhir dihitung ulang dengan bobot terkini.');
    }

    public function publish(Request $request, FinalScore $finalScore): RedirectResponse
    {
        $this->authorize('publish', $finalScore);

        $finalScore->update([
            'published_at' => CarbonImmutable::now(),
            'published_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Nilai dipublikasikan. Siswa sekarang dapat melihatnya.');
    }

    public function unpublish(FinalScore $finalScore): RedirectResponse
    {
        $this->authorize('unpublish', $finalScore);

        // published_by is kept: the activity log records who unpublished, and the
        // final_scores_publisher_check only requires a publisher while published.
        $finalScore->update(['published_at' => null]);

        return back()->with('success', 'Publikasi dibatalkan. Nilai tidak lagi terlihat siswa.');
    }
}
