<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\InMemory;

use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Repository\PlayerRepository;

final class InMemoryPlayerRepository implements PlayerRepository
{
    /** @var array<string, Player> */
    private array $players = [];

    public function save(Player $player): void
    {
        $this->players[$player->getId()->value] = $player;
    }

    public function ofId(PlayerId $id): ?Player
    {
        return $this->players[$id->value] ?? null;
    }
}
