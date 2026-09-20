<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter\View;

final readonly class TeamSummaryView
{
    /** @param PlayerSummaryView[] $players */
    public function __construct(
        public string $id,
        public string $name,
        public string $captainId,
        public array $players,
    ) {
    }
}
