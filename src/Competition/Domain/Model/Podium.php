<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final readonly class Podium
{
    public function __construct(
        public TeamId $champion,
    ) {
    }
}
