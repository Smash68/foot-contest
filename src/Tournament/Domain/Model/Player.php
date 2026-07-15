<?php

declare(strict_types=1);

namespace App\Tournament\Domain\Model;

final class Player
{
    public function __construct(
        private readonly PlayerId $id,
        private readonly string $name,
    ) {}

    public function getId(): PlayerId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}