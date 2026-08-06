<?php

declare(strict_types=1);

namespace App\Tests\Organization\Application\Login;

use App\Organization\Application\Login\LoginCommand;
use App\Organization\Application\Login\LoginHandler;
use App\Organization\Application\RegisterOrganizer\RegisterOrganizerCommand;
use App\Organization\Application\RegisterOrganizer\RegisterOrganizerHandler;
use App\Organization\Domain\Exception\InvalidCredentialsException;
use App\Organization\Infrastructure\Password\NativePasswordHasher;
use App\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizerRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoginHandlerTest extends TestCase
{
    #[Test]
    public function it_rejects_login_with_an_unknown_email(): void
    {
        $handler = new LoginHandler(new InMemoryOrganizerRepository(), new NativePasswordHasher());

        $this->expectException(InvalidCredentialsException::class);

        $handler(new LoginCommand('unknown@example.com', 'super-secret'));
    }

    #[Test]
    public function it_rejects_login_with_an_invalid_password(): void
    {
        $organizers = new InMemoryOrganizerRepository();
        $passwordHasher = new NativePasswordHasher();
        (new RegisterOrganizerHandler($organizers, $passwordHasher))(
            new RegisterOrganizerCommand('organizer@example.com', 'super-secret'),
        );
        $handler = new LoginHandler($organizers, $passwordHasher);

        $this->expectException(InvalidCredentialsException::class);

        $handler(new LoginCommand('organizer@example.com', 'wrong-password'));
    }
}
