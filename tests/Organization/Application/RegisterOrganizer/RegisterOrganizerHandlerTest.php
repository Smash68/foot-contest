<?php

declare(strict_types=1);

namespace App\Tests\Organization\Application\RegisterOrganizer;

use App\Organization\Application\RegisterOrganizer\RegisterOrganizerCommand;
use App\Organization\Application\RegisterOrganizer\RegisterOrganizerHandler;
use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Infrastructure\Password\NativePasswordHasher;
use App\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizerRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegisterOrganizerHandlerTest extends TestCase
{
    #[Test]
    public function it_registers_a_new_organizer_with_a_hashed_password(): void
    {
        $organizers = new InMemoryOrganizerRepository();
        $handler = new RegisterOrganizerHandler($organizers, new NativePasswordHasher());

        $id = $handler(new RegisterOrganizerCommand('organizer@example.com', 'super-secret'));

        self::assertInstanceOf(OrganizerId::class, $id);

        $organizer = $organizers->ofEmail('organizer@example.com');
        self::assertNotNull($organizer);
        self::assertSame('organizer@example.com', $organizer->getEmail());
        self::assertNotSame('super-secret', $organizer->getHashedPassword());
    }

    #[Test]
    public function it_rejects_registration_with_an_already_used_email(): void
    {
        $organizers = new InMemoryOrganizerRepository();
        $handler = new RegisterOrganizerHandler($organizers, new NativePasswordHasher());
        $handler(new RegisterOrganizerCommand('organizer@example.com', 'super-secret'));

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RegisterOrganizerCommand('organizer@example.com', 'another-password'));
    }
}
