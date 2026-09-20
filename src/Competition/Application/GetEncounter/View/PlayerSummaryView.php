<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter\View;

final readonly class PlayerSummaryView
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }
}
