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

    public function register(Registration $registration): void
    {
        $this->assertOpenForRegistration();

        if ($this->countRegistrations() >= $this->capacity->max) {
            throw new \LogicException("Tournament '{$this->id->value}' has reached its maximum number of teams.");
        }

        if ($this->findRegistrationIndex($registration->getTeam()->getId()) !== null) {
            throw new \LogicException("Team '{$registration->getTeam()->getId()->value}' is already registered in tournament '{$this->id->value}'.");
        }

        $this->registrations[] = $registration;
    }

    public function closeRegistration(): void
    {
        if ($this->countRegistrations() < $this->capacity->min) {
            throw new \LogicException("Tournament '{$this->id->value}' has not reached its minimum number of teams.");
        }

        $this->closed = true;
    }

    public function withdraw(TeamId $teamId): void
    {
        $this->assertOpenForRegistration();

        $index = $this->findRegistrationIndex($teamId);

        if ($index === null) {
            throw new \InvalidArgumentException("Team '{$teamId->value}' is not registered in tournament '{$this->id->value}'.");
        }

        unset($this->registrations[$index]);
    }

    private function assertOpenForRegistration(): void
    {
        if (!$this->isOpenForRegistration()) {
            throw new \LogicException("Tournament '{$this->id->value}' registration is closed.");
        }
    }

    private function findRegistrationIndex(TeamId $teamId): ?int
    {
        foreach ($this->registrations as $index => $registration) {
            if ($registration->getTeam()->getId()->equals($teamId)) {
                return $index;
            }
        }

        return null;
    }
}
