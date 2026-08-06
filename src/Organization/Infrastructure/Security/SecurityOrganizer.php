<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Security;

use App\Organization\Domain\Model\OrganizerId;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class SecurityOrganizer implements UserInterface
{
    public function __construct(private OrganizerId $organizerId)
    {
    }

    public function getRoles(): array
    {
        return [];
    }

    public function getUserIdentifier(): string
    {
        $identifier = $this->organizerId->value;
        assert($identifier !== '');

        return $identifier;
    }
}
