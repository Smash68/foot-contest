<?php

declare(strict_types=1);

namespace App\Tournament\Domain\Model;

final class Tournament
{
    /** @var Registration[] */
    private array $registrations = [];

    private bool $closed = false;

    private function __construct(
        private readonly TournamentId $id,
        private readonly string $name,
        private readonly TeamCapacity $capacity,
    ) {}

    public static function create(TournamentId $id, string $name, TeamCapacity $capacity): self
    {
        return new self($id, $name, $capacity);
    }

    public function isOpenForRegistration(): bool
    {
        return !$this->closed;
    }

    public function countRegistrations(): int
    {
        return count($this->registrations);
    }
}
