<?php

declare(strict_types=1);

namespace App\Tests\Support\Builder;

use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Model\OrganizerId;

final class OrganizationBuilder
{
    private string $id = 'organization-1';
    private string $name = 'Ligue amateur du Nord';
    private string $ownerId = 'organizer-1';

    public static function anOrganization(): self
    {
        return new self();
    }

    public function withId(string $id): self
    {
        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }

    /** The owning organizer. */
    public function ownedBy(string $ownerId): self
    {
        $clone = clone $this;
        $clone->ownerId = $ownerId;

        return $clone;
    }

    public function build(): Organization
    {
        return Organization::create(new OrganizationId($this->id), $this->name, new OrganizerId($this->ownerId));
    }
}
