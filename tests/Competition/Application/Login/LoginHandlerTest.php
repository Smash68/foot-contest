<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\Login;

use App\Competition\Application\Login\LoginCommand;
use App\Competition\Application\Login\LoginHandler;
use App\Competition\Application\RegisterPlayer\RegisterPlayerCommand;
use App\Competition\Application\RegisterPlayer\RegisterPlayerHandler;
use App\Competition\Domain\Exception\InvalidCredentialsException;
use App\Competition\Domain\Service\AccessTokenIssuer;
use App\Competition\Infrastructure\Password\NativePasswordHasher;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryPlayerRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoginHandlerTest extends TestCase
{
    #[Test]
    public function it_rejects_login_with_an_unknown_email(): void
    {
        $handler = new LoginHandler(new InMemoryPlayerRepository(), new NativePasswordHasher(), $this->accessTokenIssuerStub());

        $this->expectException(InvalidCredentialsException::class);

        $handler(new LoginCommand('unknown@example.com', 'super-secret'));
    }

    #[Test]
    public function it_rejects_login_with_an_invalid_password(): void
    {
        $players = new InMemoryPlayerRepository();
        $passwordHasher = new NativePasswordHasher();
        (new RegisterPlayerHandler($players, $passwordHasher))(
            new RegisterPlayerCommand('Captain', 'captain@example.com', 'super-secret'),
        );
        $handler = new LoginHandler($players, $passwordHasher, $this->accessTokenIssuerStub());

        $this->expectException(InvalidCredentialsException::class);

        $handler(new LoginCommand('captain@example.com', 'wrong-password'));
    }

    #[Test]
    public function it_returns_the_issued_access_token_when_credentials_are_valid(): void
    {
        $players = new InMemoryPlayerRepository();
        $passwordHasher = new NativePasswordHasher();
        (new RegisterPlayerHandler($players, $passwordHasher))(
            new RegisterPlayerCommand('Captain', 'captain@example.com', 'super-secret'),
        );
        $handler = new LoginHandler($players, $passwordHasher, $this->accessTokenIssuerStub('fake-jwt-token'));

        $token = $handler(new LoginCommand('captain@example.com', 'super-secret'));

        self::assertSame('fake-jwt-token', $token);
    }

    private function accessTokenIssuerStub(string $token = 'fake-jwt-token'): AccessTokenIssuer
    {
        $issuer = $this->createStub(AccessTokenIssuer::class);
        $issuer->method('issue')->willReturn($token);

        return $issuer;
    }
}
