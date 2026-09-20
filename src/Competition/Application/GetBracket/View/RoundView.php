<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket\View;

final readonly class RoundView implements \JsonSerializable
{
    /** @param EncounterView[] $encounters */
    public function __construct(
        public int $number,
        public array $encounters,
    ) {
    }

    /** @return array{number: int, encounters: EncounterView[]} */
    public function jsonSerialize(): array
    {
        return [
            'number' => $this->number,
            'encounters' => $this->encounters,
        ];
    }
}
