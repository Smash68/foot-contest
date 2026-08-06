<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Http;

final readonly class RegisterOrganizerRequest
{
    public function __construct(
        public string $email,
        public string $password,
    ) {
    }
}
