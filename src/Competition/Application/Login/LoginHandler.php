<?php

declare(strict_types=1);

namespace App\Competition\Application\Login;

use App\Competition\Domain\Exception\InvalidCredentialsException;
use App\Competition\Domain\Repository\PlayerRepository;
use App\Competition\Domain\Service\AccessTokenIssuer;
use App\Competition\Domain\Service\PasswordHasher;

final readonly class LoginHandler
{
    public function __construct(
        private PlayerRepository $players,
        private PasswordHasher $passwordHasher,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {
    }

    public function __invoke(LoginCommand $command): string
    {
        $player = $this->players->ofEmail($command->email);

        if ($player === null) {
            throw new InvalidCredentialsException();
        }

        if (!$this->passwordHasher->verify($command->plainPassword, $player->getHashedPassword())) {
            throw new InvalidCredentialsException();
        }

        return $this->accessTokenIssuer->issue($player->getId());
    }
}
