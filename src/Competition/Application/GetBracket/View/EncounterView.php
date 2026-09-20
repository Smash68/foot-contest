<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket\View;

final readonly class EncounterView
{
    public function __construct(
        public string $id,
        public ParticipantView $home,
        public ParticipantView $away,
        public ?EncounterResultView $result = null,
    ) {
    }
}