<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter\View;

final readonly class PlayerSummaryView implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public string $name,
    ) {
    }

    /** @return array{id: string, name: string} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
