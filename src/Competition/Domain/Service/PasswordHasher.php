<?php

declare(strict_types=1);

namespace App\Competition\Domain\Service;

interface PasswordHasher
{
    public function hash(string $plainPassword): string;

    public function verify(string $plainPassword, string $hashedPassword): bool;
}
