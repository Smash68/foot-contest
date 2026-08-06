<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Password;

use App\Organization\Infrastructure\Password\NativePasswordHasher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NativePasswordHasherTest extends TestCase
{
    #[Test]
    public function it_verifies_a_plain_password_against_its_own_hash(): void
    {
        $hasher = new NativePasswordHasher();
        $hashedPassword = $hasher->hash('super-secret');

        self::assertTrue($hasher->verify('super-secret', $hashedPassword));
    }
}
