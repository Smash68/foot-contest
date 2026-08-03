<?php

declare(strict_types=1);

namespace App\Competition\Application\CreateCompetition;

use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Repository\CompetitionRepository;

final readonly class CreateCompetitionHandler
{
    public function __construct(private CompetitionRepository $repository)
    {
    }

    public function __invoke(CreateCompetitionCommand $command): CompetitionId
    {
        $competition = Competition::create(
            $this->repository->nextIdentity(),
            $command->name,
            TeamCapacity::of($command->minTeams, $command->maxTeams),
            new BracketConfiguration(CompetitionFormat::from($command->format), $command->includeThirdPlaceMatch),
        );

        $this->repository->save($competition);

        return $competition->getId();
    }
}
