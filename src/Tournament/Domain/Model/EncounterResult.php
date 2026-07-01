<?php

declare(strict_types=1);

namespace App\Tournament\Domain\Model;

final readonly class EncounterResult
{
    private function __construct(
        public int $homeGoals,
        public int $awayGoals,
    ) {
        if ($homeGoals < 0 || $awayGoals < 0) {
            throw new \InvalidArgumentException('Scores cannot be negative.');
        }

        if ($homeGoals === $awayGoals) {
            throw new \InvalidArgumentException('A draw requires extra time (not yet supported).');
        }
    }

    public static function regularTime(int $home, int $away): self
    {
        return new self($home, $away);
    }

    public function winner(): Side
    {
        return $this->homeGoals > $this->awayGoals ? Side::Home : Side::Away;
    }
}