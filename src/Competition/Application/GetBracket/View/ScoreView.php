<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket\View;

final readonly class ScoreView
{
    public function __construct(
        public int $home,
        public int $away,
    ) {
    }
}
