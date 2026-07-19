<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final class Team
{
    /** @var PlayerId[] */
    private array $roster;

    private function __construct(
        private readonly TeamId $id,
        private readonly string $name,
        private readonly PlayerId $captainId,
    ) {
        $this->roster = [$captainId];
    }

    public static function create(TeamId $id, string $name, PlayerId $captainId): self
    {
        return new self($id, $name, $captainId);
    }

    public function getId(): TeamId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCaptainId(): PlayerId
    {
        return $this->captainId;
    }

    /** @return PlayerId[] */
    public function getRoster(): array
    {
        return $this->roster;
    }
}
