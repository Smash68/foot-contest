<?php

declare(strict_types=1);

namespace App\Tournament\Domain\Model;

final class Team
{
    public function __construct(
        private readonly TeamId $id,
        private readonly string $name,
    ) {}

    public function getId(): TeamId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}