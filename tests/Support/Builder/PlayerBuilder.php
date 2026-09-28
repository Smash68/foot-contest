<?php

declare(strict_types=1);

namespace App\Tests\Support\Builder;

use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;

final class PlayerBuilder
{
    private string $id = 'player-1';
    private ?string $email = null;
    private string $hashedPassword = 'hashed-password';

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

    /** The email defaults to "{id}@example.com"; pass one when the test looks the player up by email. */
    public function withEmail(string $email): self
    {
        $clone = clone $this;
        $clone->email = $email;

        return $clone;
    }

    /** Pass a real hash (from PasswordHasher) when the test goes through a credentials check. */
    public function withHashedPassword(string $hashedPassword): self
    {
        $clone = clone $this;
        $clone->hashedPassword = $hashedPassword;

        return $clone;
    }

    public function build(): Player
    {
        return Player::register(new PlayerId($this->id), 'Alice', $this->email ?? "{$this->id}@example.com", $this->hashedPassword);
    }
}
