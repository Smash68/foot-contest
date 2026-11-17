<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

final readonly class ScoreRequest
{
    public function __construct(
        public int $home,
        public int $away,
    ) {
    }
}
