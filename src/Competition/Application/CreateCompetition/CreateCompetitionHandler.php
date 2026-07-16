<?php

declare(strict_types=1);

namespace App\Competition\Application\CreateCompetition;

use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Repository\CompetitionRepository;

final readonly class CreateCompetitionHandler
{
    public function __construct(private CompetitionRepository $repository) {}

    public function __invoke(CreateCompetitionCommand $command): void
    {
        $competition = Competition::create(
            new CompetitionId($command->id),
            $command->name,
            TeamCapacity::of($command->minTeams, $command->maxTeams),
        );

        $this->repository->save($competition);
    }
}