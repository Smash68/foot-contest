<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter\View;

final readonly class ParticipantDetailView implements \JsonSerializable
{
    public function __construct(
        public ParticipantViewType $type,
        public ?TeamSummaryView $team = null,
        public ?string $pendingWinnerOfEncounterId = null,
    ) {
    }

    /** @return array{type: ParticipantViewType, team: ?TeamSummaryView, pendingWinnerOfEncounterId: ?string} */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'team' => $this->team,
            'pendingWinnerOfEncounterId' => $this->pendingWinnerOfEncounterId,
        ];
    }
}
