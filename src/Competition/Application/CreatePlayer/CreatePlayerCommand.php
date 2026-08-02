<?php

declare(strict_types=1);

namespace App\Competition\Application\CreatePlayer;

final readonly class CreatePlayerCommand
{
    public function __construct(
        public string $name,
        public string $email,
    ) {
    }
}
