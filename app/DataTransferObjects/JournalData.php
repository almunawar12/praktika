<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use App\Http\Requests\Journal\StoreJournalRequest;
use App\Http\Requests\Journal\UpdateJournalRequest;

/**
 * A journal's content. The date is not part of it: it is fixed when the journal is
 * created and can never move, so it travels as its own argument rather than as a
 * field that update() would have to remember to ignore.
 */
final readonly class JournalData
{
    public function __construct(
        public string $activity,
        public string $workResult,
        public ?string $obstacle,
    ) {}

    public static function fromRequest(StoreJournalRequest|UpdateJournalRequest $request): self
    {
        return new self(
            activity: (string) $request->string('activity'),
            workResult: (string) $request->string('work_result'),
            obstacle: $request->filled('obstacle') ? (string) $request->string('obstacle') : null,
        );
    }

    /**
     * @return array<string, string|null>
     */
    public function toContent(): array
    {
        return [
            'activity' => $this->activity,
            'work_result' => $this->workResult,
            'obstacle' => $this->obstacle,
        ];
    }
}
