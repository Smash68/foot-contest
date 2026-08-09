<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

use App\Competition\Domain\Service\BracketGeneratorFactory;

final class Competition
{
    /** @var Team[] */
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

        if ($this->findTeamIndex($team->getId()) !== null) {
            throw new \LogicException("Team '{$team->getId()->value}' is already registered in competition '{$this->id->value}'.");
        }

        $this->teams[] = $team;
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

        $index = $this->findTeamIndex($teamId);

        if ($index === null) {
            throw new \InvalidArgumentException("Team '{$teamId->value}' is not registered in competition '{$this->id->value}'.");
        }

        unset($this->teams[$index]);
    }

    public function getTeamCaptainId(TeamId $teamId): PlayerId
    {
        $index = $this->findTeamIndex($teamId);

        if ($index === null) {
            throw new \InvalidArgumentException("Team '{$teamId->value}' is not registered in competition '{$this->id->value}'.");
        }

        return $this->teams[$index]->getCaptainId();
    }

    private function assertOpenForRegistration(): void
    {
        if (!$this->isOpenForRegistration()) {
            throw new \LogicException("Competition '{$this->id->value}' registration is closed.");
        }
    }

    private function findTeamIndex(TeamId $teamId): ?int
    {
        foreach ($this->teams as $index => $team) {
            if ($team->getId()->equals($teamId)) {
                return $index;
            }
        }

        return null;
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
