<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CreatePlayer;

use App\Competition\Application\CreatePlayer\CreatePlayerCommand;
use App\Competition\Application\CreatePlayer\CreatePlayerHandler;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryPlayerRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreatePlayerHandlerTest extends TestCase
{
    #[Test]
    public function it_creates_and_persists_a_player(): void
    {
        $players = new InMemoryPlayerRepository();
        $handler = new CreatePlayerHandler($players);

        $handler(new CreatePlayerCommand('Captain', 'captain@example.com'));

        $player = $players->ofId(new PlayerId('captain@example.com'));
        self::assertNotNull($player);
        self::assertSame('Captain', $player->getName());
    }

    #[Test]
    public function it_does_not_overwrite_an_existing_player_with_the_same_email(): void
    {
        $players = new InMemoryPlayerRepository();
        $handler = new CreatePlayerHandler($players);

        $handler(new CreatePlayerCommand('Captain', 'captain@example.com'));
        $handler(new CreatePlayerCommand('Impostor', 'captain@example.com'));

        $player = $players->ofId(new PlayerId('captain@example.com'));
        self::assertNotNull($player);
        self::assertSame('Captain', $player->getName());
    }
}
