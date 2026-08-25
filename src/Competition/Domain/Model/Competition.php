<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

use App\Competition\Domain\Service\BracketGeneratorFactory;

final class Competition
{
    /** @var array<string, Team> */
    private array $teams = [];

    private bool $closed = false;

    private ?Bracket $bracket = null;

    private function __construct(
        private readonly CompetitionId $id,
        private readonly string $name,
        private readonly TeamCapacity $capacity,
        private readonly BracketConfiguration $bracketConfiguration,
        private readonly OrganizationId $organizationId,
    ) {
    }

    public static function create(CompetitionId $id, string $name, TeamCapacity $capacity, BracketConfiguration $bracketConfiguration, OrganizationId $organizationId): self
    {
        return new self($id, $name, $capacity, $bracketConfiguration, $organizationId);
    }

    public function getId(): CompetitionId
    {
        return $this->id;
    }

    public function getOrganizationId(): OrganizationId
    {
        return $this->organizationId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFormat(): CompetitionFormat
    {
        return $this->bracketConfiguration->format;
    }

    public function includesThirdPlaceMatch(): bool
    {
        return $this->bracketConfiguration->includeThirdPlaceMatch;
    }

    public function isOpenForRegistration(): bool
    {
        return !$this->closed;
    }

    public function countRegistrations(): int
    {
        return count($this->teams);
    }

    public function register(Team $team): void
    {
        $this->assertOpenForRegistration();

        if ($this->countRegistrations() >= $this->capacity->max) {
            throw new \LogicException("Competition '{$this->id->value}' has reached its maximum number of teams.");
        }

        if (isset($this->teams[$team->getId()->value])) {
            throw new \LogicException("Team '{$team->getId()->value}' is already registered in competition '{$this->id->value}'.");
        }

        $normalizedName = self::normalizeTeamName($team->getName());
        foreach ($this->teams as $existingTeam) {
            if (self::normalizeTeamName($existingTeam->getName()) === $normalizedName) {
                throw new \LogicException("Team name '{$team->getName()}' is already taken in competition '{$this->id->value}'.");
            }
        }

        if ($this->belongsToAnotherTeam($team->getCaptainId())) {
            throw new \LogicException("Player '{$team->getCaptainId()->value}' already belongs to a team in competition '{$this->id->value}'.");
        }

        $this->teams[$team->getId()->value] = $team;
    }

    private function belongsToAnotherTeam(PlayerId $playerId, ?TeamId $excludingTeamId = null): bool
    {
        foreach ($this->teams as $existingTeam) {
            if ($excludingTeamId !== null && $existingTeam->getId()->equals($excludingTeamId)) {
                continue;
            }

            foreach ($existingTeam->getRoster() as $rosterPlayerId) {
                if ($rosterPlayerId->equals($playerId)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function closeRegistration(): void
    {
        if ($this->countRegistrations() < $this->capacity->min) {
            throw new \LogicException("Competition '{$this->id->value}' has not reached its minimum number of teams.");
        }

        $this->closed = true;
    }

    public function withdraw(TeamId $teamId): void
    {
        $this->assertOpenForRegistration();

        $this->getTeam($teamId);

        unset($this->teams[$teamId->value]);
    }

    public function getTeamCaptainId(TeamId $teamId): PlayerId
    {
        return $this->getTeam($teamId)->getCaptainId();
    }

    public function requestToJoinTeam(TeamId $teamId, PlayerId $playerId): void
    {
        $this->assertOpenForRegistration();

        $team = $this->getTeam($teamId);

        if ($this->belongsToAnotherTeam($playerId, $teamId)) {
            throw new \LogicException("Player '{$playerId->value}' already belongs to a team in competition '{$this->id->value}'.");
        }

        $team->requestToJoin($playerId);
    }

    /** @return PlayerId[] */
    public function getTeamPendingRequests(TeamId $teamId): array
    {
        return $this->getTeam($teamId)->getPendingRequests();
    }

    public function approveJoinRequest(TeamId $teamId, PlayerId $playerId): void
    {
        $this->assertOpenForRegistration();

        if ($this->belongsToAnotherTeam($playerId, $teamId)) {
            throw new \LogicException("Player '{$playerId->value}' already belongs to a team in competition '{$this->id->value}'.");
        }

        $this->getTeam($teamId)->approveJoinRequest($playerId);
    }

    /** @return PlayerId[] */
    public function getTeamRoster(TeamId $teamId): array
    {
        return $this->getTeam($teamId)->getRoster();
    }

    public function rejectJoinRequest(TeamId $teamId, PlayerId $playerId): void
    {
        $this->assertOpenForRegistration();

        $this->getTeam($teamId)->rejectJoinRequest($playerId);
    }

    public function removePlayerFromTeam(TeamId $teamId, PlayerId $playerId): void
    {
        $this->assertOpenForRegistration();

        $this->getTeam($teamId)->removeFromRoster($playerId);
    }

    private function assertOpenForRegistration(): void
    {
        if (!$this->isOpenForRegistration()) {
            throw new \LogicException("Competition '{$this->id->value}' registration is closed.");
        }
    }

    private static function normalizeTeamName(string $name): string
    {
        return mb_strtolower(trim($name));
    }

    private function getTeam(TeamId $teamId): Team
    {
        if (!isset($this->teams[$teamId->value])) {
            throw new \InvalidArgumentException("Team '{$teamId->value}' is not registered in competition '{$this->id->value}'.");
        }

        return $this->teams[$teamId->value];
    }

    public function generateBracket(BracketGeneratorFactory $factory): void
    {
        if ($this->isOpenForRegistration()) {
            throw new \LogicException("Competition '{$this->id->value}' registration must be closed before generating the bracket.");
        }

        if ($this->bracket !== null) {
            throw new \LogicException("Competition '{$this->id->value}' bracket has already been generated.");
        }

        $teamIds = array_map(
            fn (Team $team) => $team->getId(),
            $this->teams,
        );

        $this->bracket = $factory->forConfiguration($this->bracketConfiguration)->generate($teamIds);
    }

    public function getBracket(): ?Bracket
    {
        return $this->bracket;
    }
}
