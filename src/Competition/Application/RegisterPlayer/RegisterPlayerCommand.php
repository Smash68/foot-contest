<?php

declare(strict_types=1);

namespace App\Competition\Application\RegisterPlayer;

final readonly class RegisterPlayerCommand
{
    public function __construct(
        public string $name,
        public string $email,
        public string $plainPassword,
    ) {
    }
}
