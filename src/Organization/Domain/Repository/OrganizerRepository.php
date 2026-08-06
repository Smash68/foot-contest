<?php

declare(strict_types=1);

namespace App\Organization\Domain\Repository;

use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Model\OrganizerId;

interface OrganizerRepository
{
    public function nextIdentity(): OrganizerId;

    public function save(Organizer $organizer): void;

    public function ofEmail(string $email): ?Organizer;
}
