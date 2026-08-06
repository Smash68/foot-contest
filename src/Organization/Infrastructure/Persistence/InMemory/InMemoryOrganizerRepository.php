<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\InMemory;

use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Domain\Repository\OrganizerRepository;
use Symfony\Component\Uid\Uuid;

final class InMemoryOrganizerRepository implements OrganizerRepository
{
    /** @var array<string, Organizer> */
    private array $organizers = [];

    public function nextIdentity(): OrganizerId
    {
        return new OrganizerId(Uuid::v7()->toRfc4122());
    }

    public function save(Organizer $organizer): void
    {
        $this->organizers[$organizer->getId()->value] = $organizer;
    }

    public function ofEmail(string $email): ?Organizer
    {
        foreach ($this->organizers as $organizer) {
            if ($organizer->getEmail() === $email) {
                return $organizer;
            }
        }

        return null;
    }
}
