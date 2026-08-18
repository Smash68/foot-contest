<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final class Team
{
    /** @var array<string, PlayerId> */
    private array $roster;

    /** @var array<string, PlayerId> */
    private array $pendingRequests = [];

    private function __construct(
        private readonly TeamId $id,
        private readonly string $name,
        private readonly PlayerId $captainId,
    ) {
        $this->roster = [$captainId->value => $captainId];
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
        return array_values($this->roster);
    }

    public function requestToJoin(PlayerId $playerId): void
    {
        if (isset($this->roster[$playerId->value])) {
            throw new \LogicException("Player '{$playerId->value}' is already in the roster of team '{$this->id->value}'.");
        }

        $this->pendingRequests[$playerId->value] = $playerId;
    }

    /** @return PlayerId[] */
    public function getPendingRequests(): array
    {
        return array_values($this->pendingRequests);
    }

    public function approveJoinRequest(PlayerId $playerId): void
    {
        if (isset($this->roster[$playerId->value])) {
            return;
        }

        if (!isset($this->pendingRequests[$playerId->value])) {
            throw new \InvalidArgumentException("Player '{$playerId->value}' has no pending join request for team '{$this->id->value}'.");
        }

        unset($this->pendingRequests[$playerId->value]);
        $this->roster[$playerId->value] = $playerId;
    }

    public function rejectJoinRequest(PlayerId $playerId): void
    {
        if (!isset($this->pendingRequests[$playerId->value])) {
            throw new \InvalidArgumentException("Player '{$playerId->value}' has no pending join request for team '{$this->id->value}'.");
        }

        unset($this->pendingRequests[$playerId->value]);
    }
}
