<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

final readonly class LoginRequest
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
