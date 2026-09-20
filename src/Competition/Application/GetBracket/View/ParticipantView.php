<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket\View;

final readonly class ParticipantView
{
    public function __construct(
        public ParticipantViewType $type,
        public ?string $teamId = null,
        public ?string $pendingWinnerOfEncounterId = null,
    ) {
    }
}