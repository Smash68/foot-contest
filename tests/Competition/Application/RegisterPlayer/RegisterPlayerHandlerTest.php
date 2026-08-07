<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\RegisterPlayer;

use App\Competition\Application\RegisterPlayer\RegisterPlayerCommand;
use App\Competition\Application\RegisterPlayer\RegisterPlayerHandler;
use App\Competition\Infrastructure\Password\NativePasswordHasher;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryPlayerRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RegisterPlayerHandlerTest extends TestCase
{
    #[Test]
    public function it_registers_and_persists_a_player_with_a_hashed_password(): void
    {
        $players = new InMemoryPlayerRepository();
        $handler = new RegisterPlayerHandler($players, new NativePasswordHasher());

        $id = $handler(new RegisterPlayerCommand('Captain', 'captain@example.com', 'super-secret'));

        $player = $players->ofEmail('captain@example.com');
        self::assertNotNull($player);
        self::assertTrue($id->equals($player->getId()));
        self::assertSame('Captain', $player->getName());
        self::assertSame('captain@example.com', $player->getEmail());
        self::assertNotSame('super-secret', $player->getHashedPassword());
    }

    #[Test]
    public function it_does_not_overwrite_an_existing_player_with_the_same_email(): void
    {
        $players = new InMemoryPlayerRepository();
        $handler = new RegisterPlayerHandler($players, new NativePasswordHasher());

        $firstId = $handler(new RegisterPlayerCommand('Captain', 'captain@example.com', 'super-secret'));
        $secondId = $handler(new RegisterPlayerCommand('Impostor', 'captain@example.com', 'another-password'));

        self::assertTrue($firstId->equals($secondId));

        $player = $players->ofEmail('captain@example.com');
        self::assertNotNull($player);
        self::assertSame('Captain', $player->getName());
    }
}
