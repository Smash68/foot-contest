<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket\View;

final readonly class BracketView
{
    /** @param RoundView[] $rounds */
    public function __construct(
        public array $rounds,
        public bool $isComplete,
        public ?string $champion = null,
    ) {
    }
}