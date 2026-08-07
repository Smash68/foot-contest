<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

final readonly class RegisterPlayerRequest
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
    ) {
    }
}
