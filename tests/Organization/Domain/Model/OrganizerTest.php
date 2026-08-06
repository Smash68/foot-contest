<?php

declare(strict_types=1);

namespace App\Tests\Organization\Domain\Model;

use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Model\OrganizerId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrganizerTest extends TestCase
{
    #[Test]
    public function it_registers_with_a_valid_email_and_a_hashed_password(): void
    {
        $id = new OrganizerId('11111111-1111-1111-1111-111111111111');

        $organizer = Organizer::register($id, 'organizer@example.com', 'hashed-password');

        self::assertTrue($id->equals($organizer->getId()));
        self::assertSame('organizer@example.com', $organizer->getEmail());
        self::assertSame('hashed-password', $organizer->getHashedPassword());
    }

    #[Test]
    public function it_rejects_an_invalid_email_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Organizer::register(new OrganizerId('11111111-1111-1111-1111-111111111111'), 'not-an-email', 'hashed-password');
    }
}
