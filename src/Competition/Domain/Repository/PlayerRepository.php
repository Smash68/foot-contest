<?php

declare(strict_types=1);

namespace App\Competition\Domain\Repository;

use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;

interface PlayerRepository
{
    public function nextIdentity(): PlayerId;

    public function save(Player $player): void;

    public function ofId(PlayerId $id): ?Player;

    public function ofEmail(string $email): ?Player;
}
