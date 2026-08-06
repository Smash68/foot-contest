<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Password;

use App\Organization\Domain\Service\PasswordHasher;

final class NativePasswordHasher implements PasswordHasher
{
    public function hash(string $plainPassword): string
    {
        return password_hash($plainPassword, PASSWORD_BCRYPT);
    }
}
