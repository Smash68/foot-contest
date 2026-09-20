<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter\View;

final readonly class ParticipantDetailView
{
    public function __construct(
        public ParticipantViewType $type,
        public ?TeamSummaryView $team = null,
        public ?string $pendingWinnerOfEncounterId = null,
    ) {
    }
}
