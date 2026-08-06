<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

final readonly class OrganizationId implements \Stringable
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('OrganizationId cannot be empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
