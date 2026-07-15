<?php

declare(strict_types=1);

namespace App\Tournament\Domain\Model;

final readonly class Registration
{
    public function __construct(
        private Team $team,
        private Player $captain,
    ) {}

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function getCaptain(): Player
    {
        return $this->captain;
    }
}
