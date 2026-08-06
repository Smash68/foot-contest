<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

final class Organization
{
    private function __construct(
        private readonly OrganizationId $id,
        private readonly string $name,
        private readonly OrganizerId $ownerId,
    ) {
    }

    public static function create(OrganizationId $id, string $name, OrganizerId $ownerId): self
    {
        return new self($id, $name, $ownerId);
    }

    public function getId(): OrganizationId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getOwnerId(): OrganizerId
    {
        return $this->ownerId;
    }
}
