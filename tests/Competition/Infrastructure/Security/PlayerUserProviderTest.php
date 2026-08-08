<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Security;

use App\Competition\Domain\Model\Player;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryPlayerRepository;
use App\Competition\Infrastructure\Security\PlayerUserProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class PlayerUserProviderTest extends TestCase
{
    #[Test]
    public function it_loads_a_player_by_its_identifier(): void
    {
        $players = new InMemoryPlayerRepository();
        $id = $players->nextIdentity();
        $players->save(Player::register($id, 'Player', 'player@example.com', 'hashed-password'));
        $provider = new PlayerUserProvider($players);

        $user = $provider->loadUserByIdentifier($id->value);

        self::assertSame($id->value, $user->getUserIdentifier());
    }

    #[Test]
    public function it_throws_when_the_player_does_not_exist(): void
    {
        $provider = new PlayerUserProvider(new InMemoryPlayerRepository());

        $this->expectException(UserNotFoundException::class);

        $provider->loadUserByIdentifier('11111111-1111-1111-1111-111111111111');
    }
}
