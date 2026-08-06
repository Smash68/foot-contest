<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Security;

use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Domain\Repository\OrganizerRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<SecurityOrganizer>
 */
final readonly class OrganizerUserProvider implements UserProviderInterface
{
    public function __construct(private OrganizerRepository $organizers)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $organizer = $this->organizers->ofId(new OrganizerId($identifier));

        if ($organizer === null) {
            throw new UserNotFoundException("Organizer '{$identifier}' not found.");
        }

        return new SecurityOrganizer($organizer->getId());
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityOrganizer) {
            throw new UnsupportedUserException($user::class.' is not supported.');
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityOrganizer::class === $class;
    }
}
