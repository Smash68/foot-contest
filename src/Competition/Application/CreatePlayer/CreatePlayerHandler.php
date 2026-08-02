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
        $id = new PlayerId($command->email);

        $existing = $this->repository->ofId($id);
        if ($existing !== null) {
            return $existing->getId();
        }

        $this->repository->save(new Player($id, $command->name));

        return $id;
    }
}
