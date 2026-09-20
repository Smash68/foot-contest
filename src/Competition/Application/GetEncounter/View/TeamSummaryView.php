<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter\View;

final readonly class TeamSummaryView implements \JsonSerializable
{
    /** @param PlayerSummaryView[] $players */
    public function __construct(
        public string $id,
        public string $name,
        public string $captainId,
        public array $players,
    ) {
    }

    /** @return array{id: string, name: string, captainId: string, players: PlayerSummaryView[]} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'captainId' => $this->captainId,
            'players' => $this->players,
        ];
    }
}
