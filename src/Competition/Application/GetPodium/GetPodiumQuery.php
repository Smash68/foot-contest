<?php

declare(strict_types=1);

namespace App\Competition\Application\GetPodium;

final readonly class GetPodiumQuery
{
    public function __construct(public string $competitionId)
    {
    }
}
