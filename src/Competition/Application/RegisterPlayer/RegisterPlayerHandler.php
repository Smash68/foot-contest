<?php

declare(strict_types=1);

namespace App\Competition\Application\RegisterPlayer;

use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Repository\PlayerRepository;
use App\Competition\Domain\Service\PasswordHasher;

final readonly class RegisterPlayerHandler
{
    public function __construct(
        private PlayerRepository $repository,
        private PasswordHasher $passwordHasher,
    ) {
    }

    public function __invoke(RegisterPlayerCommand $command): PlayerId
    {
        $existing = $this->repository->ofEmail($command->email);
        if ($existing !== null) {
            return $existing->getId();
        }

        $id = $this->repository->nextIdentity();
        $hashedPassword = $this->passwordHasher->hash($command->plainPassword);
        $this->repository->save(Player::register($id, $command->name, $command->email, $hashedPassword));

        return $id;
    }
}
