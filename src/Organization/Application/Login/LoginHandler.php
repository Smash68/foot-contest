<?php

declare(strict_types=1);

namespace App\Organization\Application\Login;

use App\Organization\Domain\Exception\InvalidCredentialsException;
use App\Organization\Domain\Repository\OrganizerRepository;
use App\Organization\Domain\Service\AccessTokenIssuer;
use App\Organization\Domain\Service\PasswordHasher;

final readonly class LoginHandler
{
    public function __construct(
        private OrganizerRepository $organizers,
        private PasswordHasher $passwordHasher,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {
    }

    public function __invoke(LoginCommand $command): string
    {
        $organizer = $this->organizers->ofEmail($command->email);

        if ($organizer === null) {
            throw new InvalidCredentialsException();
        }

        if (!$this->passwordHasher->verify($command->plainPassword, $organizer->getHashedPassword())) {
            throw new InvalidCredentialsException();
        }

        return $this->accessTokenIssuer->issue($organizer->getId());
    }
}
