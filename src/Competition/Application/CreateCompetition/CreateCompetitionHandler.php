<?php

declare(strict_types=1);

namespace App\Competition\Application\CreateCompetition;

use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Service\OrganizerOrganizationAuthorization;

final readonly class CreateCompetitionHandler
{
    public function __construct(
        private CompetitionRepository $repository,
        private OrganizerOrganizationAuthorization $authorization,
    ) {
    }

    public function __invoke(CreateCompetitionCommand $command): CompetitionId
    {
        $organizationId = new OrganizationId($command->organizationId);

        if (!$this->authorization->authorizes($command->organizerId, $organizationId)) {
            throw new OrganizerNotAuthorizedForOrganizationException($command->organizerId, $command->organizationId);
        }

        $competition = Competition::create(
            $this->repository->nextIdentity(),
            $command->name,
            TeamCapacity::of($command->minTeams, $command->maxTeams),
            new BracketConfiguration(CompetitionFormat::fromValue($command->format), $command->includeThirdPlaceMatch),
            $organizationId,
        );

        $this->repository->save($competition);

        return $competition->getId();
    }
}
