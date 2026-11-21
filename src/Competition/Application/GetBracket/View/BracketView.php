<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket\View;

final readonly class BracketView implements \JsonSerializable
{
    /** @param RoundView[] $rounds */
    public function __construct(
        public array $rounds,
        public bool $isComplete,
    ) {
    }

    /** @return array{rounds: RoundView[], isComplete: bool} */
    public function jsonSerialize(): array
    {
        return [
            'rounds' => $this->rounds,
            'isComplete' => $this->isComplete,
        ];
    }
}
