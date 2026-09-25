<?php

declare(strict_types=1);

namespace App\Tests\Support\Builder;

use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;

final class PlayerBuilder
{
    private string $id = 'player-1';

    public static function aPlayer(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }

    public function build(): Player
    {
        return Player::register(new PlayerId($this->id), 'Alice', "{$this->id}@example.com", 'hashed-password');
    }
}
