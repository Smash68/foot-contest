<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket\View;

final readonly class ScoreView implements \JsonSerializable
{
    public function __construct(
        public int $home,
        public int $away,
    ) {
    }

    /** @return array{home: int, away: int} */
    public function jsonSerialize(): array
    {
        return [
            'home' => $this->home,
            'away' => $this->away,
        ];
    }
}
