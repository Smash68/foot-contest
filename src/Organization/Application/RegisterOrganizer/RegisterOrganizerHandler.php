<?php

declare(strict_types=1);

namespace App\Organization\Application\RegisterOrganizer;

use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Domain\Repository\OrganizerRepository;
use App\Organization\Domain\Service\PasswordHasher;

final readonly class RegisterOrganizerHandler
{
    public function __construct(
        private OrganizerRepository $organizers,
        private PasswordHasher $passwordHasher,
    ) {
    }

    public function __invoke(RegisterOrganizerCommand $command): OrganizerId
    {
        if ($this->organizers->ofEmail($command->email) !== null) {
            throw new \InvalidArgumentException("Email '{$command->email}' is already used.");
        }

        $id = $this->organizers->nextIdentity();
        $hashedPassword = $this->passwordHasher->hash($command->plainPassword);

        $organizer = Organizer::register($id, $command->email, $hashedPassword);

        $this->organizers->save($organizer);

        return $id;
    }
}
