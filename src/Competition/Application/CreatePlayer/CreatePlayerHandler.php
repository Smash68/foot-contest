<?php

declare(strict_types=1);

namespace App\Competition\Application\CreatePlayer;

use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Repository\PlayerRepository;

final readonly class CreatePlayerHandler
{
    public function __construct(private PlayerRepository $repository)
    {
    }

    public function __invoke(CreatePlayerCommand $command): PlayerId
    {
        $existing = $this->repository->ofEmail($command->email);
        if ($existing !== null) {
            return $existing->getId();
        }

        $id = $this->repository->nextIdentity();
        $this->repository->save(Player::register($id, $command->name, $command->email));

        return $id;
    }
}
