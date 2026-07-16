<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AssessmentType;
use App\Exceptions\AssessmentRuleException;
use App\Models\Assessment;
use App\Models\FinalScore;
use App\Models\Placement;
use Illuminate\Support\Facades\DB;

/**
 * The single place the final-score formula lives (ADD § 8.1).
 *
 * Any other module that needs a final score asks this service; duplicating the
 * arithmetic anywhere else is how two screens end up disagreeing about a student's
 * result.
 */
class ScoreCalculatorService
{
    public function __construct(private readonly SettingService $settings) {}

    /**
     * @throws AssessmentRuleException
     */
    public function calculate(Placement $placement): FinalScore
    {
        $existing = FinalScore::query()->where('placement_id', $placement->id)->first();

        // A published score is a result the school has already handed out. Changing
        // it silently would be worse than refusing.
        if ($existing?->isPublished()) {
            throw AssessmentRuleException::alreadyPublished();
        }

        $industry = $this->submittedAssessment($placement, AssessmentType::Industry);
        $teacher = $this->submittedAssessment($placement, AssessmentType::Teacher);

        if ($industry === null || $teacher === null) {
            throw AssessmentRuleException::incomplete();
        }

        $weights = $this->settings->weights();

        $industryScore = (float) $industry->average_score;
        $teacherScore = (float) $teacher->average_score;

        $final = round(
            $industryScore * $weights['industry'] + $teacherScore * $weights['teacher'],
            2,
        );

        return DB::transaction(fn (): FinalScore => FinalScore::updateOrCreate(
            ['placement_id' => $placement->id],
            [
                'industry_score' => $industryScore,
                'teacher_score' => $teacherScore,
                // Snapshot (ADR-03): the weights that produced this number travel with
                // it, so changing 60/40 for next year cannot rewrite what this score
                // meant.
                'industry_weight' => $weights['industry'],
                'teacher_weight' => $weights['teacher'],
                'final_score' => $final,
                'grade' => $this->toGrade($final),
            ],
        ));
    }

    /**
     * Recalculate if — and only if — it is safe and possible.
     *
     * Called from AssessmentObserver on every assessment save, so it must stay quiet
     * when the pair is not complete yet rather than throwing on an ordinary draft.
     */
    public function recalculateIfReady(Placement $placement): ?FinalScore
    {
        try {
            return $this->calculate($placement);
        } catch (AssessmentRuleException) {
            return null;
        }
    }

    public function toGrade(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            $score >= 60 => 'D',
            default => 'E',
        };
    }

    /**
     * Whether both sides have submitted — the only state a final score can exist in.
     */
    public function isComplete(Placement $placement): bool
    {
        return Assessment::query()
            ->where('placement_id', $placement->id)
            ->submitted()
            ->count() === count(AssessmentType::cases());
    }

    private function submittedAssessment(Placement $placement, AssessmentType $type): ?Assessment
    {
        return Assessment::query()
            ->where('placement_id', $placement->id)
            ->where('type', $type)
            ->submitted()
            ->first();
    }
}
