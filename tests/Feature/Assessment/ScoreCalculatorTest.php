<?php

declare(strict_types=1);

use App\Enums\AssessmentType;
use App\Enums\RoleName;
use App\Exceptions\AssessmentRuleException;
use App\Models\Assessment;
use App\Models\FinalScore;
use App\Models\Placement;
use App\Services\ScoreCalculatorService;
use App\Services\SettingService;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    $this->placement = Placement::factory()->completed()->create();
    $this->calculator = app(ScoreCalculatorService::class);
    $this->settings = app(SettingService::class);

    $this->settings->set(SettingService::WEIGHT_INDUSTRY, 0.60);
    $this->settings->set(SettingService::WEIGHT_TEACHER, 0.40);
});

function submitBoth(Placement $placement, float $industry, float $teacher): void
{
    Assessment::factory()->industry()->submitted($industry)->create([
        'placement_id' => $placement->id,
    ]);
    Assessment::factory()->teacher()->submitted($teacher)->create([
        'placement_id' => $placement->id,
    ]);
}

test('the final score is the weighted 60 40 split from ADD 8.1', function (): void {
    submitBoth($this->placement, 90.0, 80.0);

    $score = $this->calculator->calculate($this->placement->fresh());

    // 90 * 0.6 + 80 * 0.4 = 54 + 32 = 86
    expect((float) $score->final_score)->toBe(86.0)
        ->and($score->grade)->toBe('B');
});

test('the weights that produced a score travel with it', function (): void {
    submitBoth($this->placement, 90.0, 80.0);

    $score = $this->calculator->calculate($this->placement->fresh());

    expect((float) $score->industry_weight)->toBe(0.6)
        ->and((float) $score->teacher_weight)->toBe(0.4);
});

test('changing the weights never rewrites a score already calculated', function (): void {
    submitBoth($this->placement, 90.0, 80.0);
    $score = $this->calculator->calculate($this->placement->fresh());

    // The school switches to 50/50 for the next intake.
    $this->settings->set(SettingService::WEIGHT_INDUSTRY, 0.50);
    $this->settings->set(SettingService::WEIGHT_TEACHER, 0.50);

    // ADR-03: the stored score keeps its own snapshot, so what it meant does not
    // change under it.
    $score->refresh();

    expect((float) $score->final_score)->toBe(86.0)
        ->and((float) $score->industry_weight)->toBe(0.6);
});

test('recalculating applies the new weights', function (): void {
    submitBoth($this->placement, 90.0, 80.0);
    $this->calculator->calculate($this->placement->fresh());

    $this->settings->set(SettingService::WEIGHT_INDUSTRY, 0.50);
    $this->settings->set(SettingService::WEIGHT_TEACHER, 0.50);

    $score = $this->calculator->calculate($this->placement->fresh());

    // 90 * 0.5 + 80 * 0.5 = 85
    expect((float) $score->final_score)->toBe(85.0)
        ->and((float) $score->industry_weight)->toBe(0.5);
});

test('a score can not be calculated until both sides have submitted', function (): void {
    Assessment::factory()->industry()->submitted(90.0)->create([
        'placement_id' => $this->placement->id,
    ]);

    expect(fn () => $this->calculator->calculate($this->placement->fresh()))
        ->toThrow(AssessmentRuleException::class);

    expect(FinalScore::count())->toBe(0);
});

test('a draft assessment does not count as submitted', function (): void {
    Assessment::factory()->industry()->submitted(90.0)->create([
        'placement_id' => $this->placement->id,
    ]);
    // Teacher side saved but never sent.
    Assessment::factory()->teacher()->create(['placement_id' => $this->placement->id]);

    expect(fn () => $this->calculator->calculate($this->placement->fresh()))
        ->toThrow(AssessmentRuleException::class);
});

test('a published score is never recalculated', function (): void {
    submitBoth($this->placement, 90.0, 80.0);
    $score = $this->calculator->calculate($this->placement->fresh());
    $score->update(['published_at' => now(), 'published_by' => userWithRole(RoleName::AdminPkl)->id]);

    // Changing a result already handed out silently would be worse than refusing.
    expect(fn () => $this->calculator->calculate($this->placement->fresh()))
        ->toThrow(AssessmentRuleException::class);
});

test('grades follow the ADD boundaries', function (float $score, string $grade): void {
    expect($this->calculator->toGrade($score))->toBe($grade);
})->with([
    [100.0, 'A'],
    [90.0, 'A'],
    [89.99, 'B'],
    [80.0, 'B'],
    [79.99, 'C'],
    [70.0, 'C'],
    [69.99, 'D'],
    [60.0, 'D'],
    [59.99, 'E'],
    [0.0, 'E'],
]);

test('weights that do not split 100 percent fall back to the default', function (): void {
    // A hand-edited row must not silently corrupt every calculation.
    $this->settings->set(SettingService::WEIGHT_INDUSTRY, 0.70);
    $this->settings->set(SettingService::WEIGHT_TEACHER, 0.70);

    expect($this->settings->weights())->toBe(['industry' => 0.60, 'teacher' => 0.40]);
});

test('the database refuses weights that do not split 100 percent', function (): void {
    expect(fn () => FinalScore::factory()->create([
        'industry_weight' => 0.7,
        'teacher_weight' => 0.7,
    ]))->toThrow(QueryException::class);
});

test('isComplete reports whether both sides are in', function (): void {
    expect($this->calculator->isComplete($this->placement))->toBeFalse();

    submitBoth($this->placement, 90.0, 80.0);

    expect($this->calculator->isComplete($this->placement->fresh()))->toBeTrue();
});

test('the average comes from the criteria rows behind it', function (): void {
    $assessment = Assessment::factory()->industry()->withScores(88.0)->create([
        'placement_id' => $this->placement->id,
    ]);

    expect($assessment->scores)->toHaveCount(count(AssessmentType::Industry->criteria()))
        ->and((float) $assessment->fresh()->average_score)->toBe(88.0);
});
