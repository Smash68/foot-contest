<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Password;

use App\Competition\Domain\Service\PasswordHasher;

final class NativePasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT);
    }

    public function verify(string $plainPassword, string $hashedPassword): bool
    {
        return password_verify($plainPassword, $hashedPassword);
    }
}
