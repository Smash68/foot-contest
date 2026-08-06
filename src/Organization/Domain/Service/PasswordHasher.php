<?php

declare(strict_types=1);

namespace App\Organization\Domain\Service;

interface PasswordHasher
{
    public function hash(string $plainPassword): string;
}
