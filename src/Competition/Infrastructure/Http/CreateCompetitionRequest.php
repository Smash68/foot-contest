<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

final readonly class CreateCompetitionRequest
{
    public function __construct(
        public string $name,
        public int $minTeams,
        public int $maxTeams,
        public string $format,
        public bool $includeThirdPlaceMatch,
        public string $organizationId,
    ) {
    }
}
