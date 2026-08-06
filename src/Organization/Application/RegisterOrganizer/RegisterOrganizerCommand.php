<?php

declare(strict_types=1);

namespace App\Organization\Application\RegisterOrganizer;

final readonly class RegisterOrganizerCommand
{
    public function __construct(
        public string $email,
        public string $plainPassword,
    ) {
    }
}
