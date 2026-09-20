<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket\View;

final readonly class BracketView implements \JsonSerializable
{
    /** @param RoundView[] $rounds */
    public function __construct(
        public array $rounds,
        public bool $isComplete,
        public ?string $champion = null,
    ) {
    }

    /** @return array{rounds: RoundView[], isComplete: bool, champion: ?string} */
    public function jsonSerialize(): array
    {
        return [
            'rounds' => $this->rounds,
            'isComplete' => $this->isComplete,
            'champion' => $this->champion,
        ];
    }
}
