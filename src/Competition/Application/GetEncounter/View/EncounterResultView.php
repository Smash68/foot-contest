<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter\View;

final readonly class EncounterResultView implements \JsonSerializable
{
    public function __construct(
        public ScoreView $regularTime,
        public ?ScoreView $extraTime = null,
        public ?ScoreView $penalties = null,
    ) {
    }

    /** @return array{regularTime: ScoreView, extraTime: ?ScoreView, penalties: ?ScoreView} */
    public function jsonSerialize(): array
    {
        return [
            'regularTime' => $this->regularTime,
            'extraTime' => $this->extraTime,
            'penalties' => $this->penalties,
        ];
    }
}
