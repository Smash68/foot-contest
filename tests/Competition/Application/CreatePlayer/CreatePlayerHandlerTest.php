<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CreatePlayer;

use App\Competition\Application\CreatePlayer\CreatePlayerCommand;
use App\Competition\Application\CreatePlayer\CreatePlayerHandler;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryPlayerRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreatePlayerHandlerTest extends TestCase
{
    #[Test]
    public function it_creates_and_persists_a_player_with_a_generated_id(): void
    {
        $players = new InMemoryPlayerRepository();
        $handler = new CreatePlayerHandler($players);

        $id = $handler(new CreatePlayerCommand('Captain', 'captain@example.com'));

        $player = $players->ofEmail('captain@example.com');
        self::assertNotNull($player);
        self::assertTrue($id->equals($player->getId()));
        self::assertSame('Captain', $player->getName());
        self::assertSame('captain@example.com', $player->getEmail());
    }

    #[Test]
    public function it_does_not_overwrite_an_existing_player_with_the_same_email(): void
    {
        $players = new InMemoryPlayerRepository();
        $handler = new CreatePlayerHandler($players);

        $firstId = $handler(new CreatePlayerCommand('Captain', 'captain@example.com'));
        $secondId = $handler(new CreatePlayerCommand('Impostor', 'captain@example.com'));

        self::assertTrue($firstId->equals($secondId));

        $player = $players->ofEmail('captain@example.com');
        self::assertNotNull($player);
        self::assertSame('Captain', $player->getName());
    }
}
