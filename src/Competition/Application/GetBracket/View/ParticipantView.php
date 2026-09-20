<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket\View;

final readonly class ParticipantView implements \JsonSerializable
{
    public function __construct(
        public ParticipantViewType $type,
        public ?string $teamId = null,
        public ?string $pendingWinnerOfEncounterId = null,
    ) {
    }

    /** @return array{type: ParticipantViewType, teamId: ?string, pendingWinnerOfEncounterId: ?string} */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'teamId' => $this->teamId,
            'pendingWinnerOfEncounterId' => $this->pendingWinnerOfEncounterId,
        ];
    }
}
