<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final readonly class Score
{
    private function __construct(
        public int $home,
        public int $away,
    ) {
        if ($home < 0 || $away < 0) {
            throw new \InvalidArgumentException('Score cannot be negative.');
        }
    }

    public static function of(int $home, int $away): self
    {
        return new self($home, $away);
    }

    public function isDraw(): bool
    {
        return $this->home === $this->away;
    }
}
