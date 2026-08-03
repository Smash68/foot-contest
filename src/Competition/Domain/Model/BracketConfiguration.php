<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final readonly class BracketConfiguration
{
    public function __construct(
        public CompetitionFormat $format,
        public bool $includeThirdPlaceMatch,
    ) {
    }
}
