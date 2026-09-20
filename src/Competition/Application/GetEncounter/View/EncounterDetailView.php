<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter\View;

final readonly class EncounterDetailView
{
    public function __construct(
        public string $id,
        public ParticipantDetailView $home,
        public ParticipantDetailView $away,
        public ?EncounterResultView $result = null,
    ) {
    }
}
