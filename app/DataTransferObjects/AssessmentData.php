<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Enums\AssessmentCriteria;
use App\Http\Requests\Assessment\StoreAssessmentRequest;

final readonly class AssessmentData
{
    /**
     * @param  array<string, float>  $scores  criteria code => score
     */
    public function __construct(
        public array $scores,
        public ?string $note,
        public bool $submit,
    ) {}

    public static function fromRequest(StoreAssessmentRequest $request): self
    {
        /** @var array<string, mixed> $raw */
        $raw = $request->input('scores', []);

        $scores = [];

        foreach ($raw as $code => $value) {
            // Anything not in the enum was already rejected by validation; this keeps
            // the DTO from carrying it further regardless.
            if (AssessmentCriteria::tryFrom((string) $code) !== null) {
                $scores[(string) $code] = (float) $value;
            }
        }

        return new self(
            scores: $scores,
            note: $request->filled('note') ? (string) $request->string('note') : null,
            // A draft save and a submit are the same form; only this differs.
            submit: $request->boolean('submit'),
        );
    }

    public function average(): float
    {
        if ($this->scores === []) {
            return 0.0;
        }

        return round(array_sum($this->scores) / count($this->scores), 2);
    }
}
