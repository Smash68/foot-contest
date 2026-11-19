<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final readonly class Podium
{
    public function __construct(
        public TeamId $champion,
        public TeamId $runnerUp,
        public ?TeamId $third = null,
    ) {
    }

    public function withThird(TeamId $third): self
    {
        return new self($this->champion, $this->runnerUp, $third);
    }
}
