<?php

declare(strict_types=1);

namespace App\Competition\Application\RemovePlayerFromTeam;

use App\Competition\Domain\Exception\NotAuthorizedToRemoveTeamMemberException;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Service\OrganizerOrganizationAuthorization;

final readonly class RemovePlayerFromTeamHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
        private OrganizerOrganizationAuthorization $authorization,
    ) {
    }

    public function __invoke(RemovePlayerFromTeamCommand $command): void
    {
        $competition = $this->competitions->ofId(new CompetitionId($command->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$command->competitionId}' does not exist.");
        }

        $teamId = new TeamId($command->teamId);

        if (!$this->isAuthorizedToRemove($competition, $teamId, $command->playerId, $command->actorId)) {
            throw new NotAuthorizedToRemoveTeamMemberException($command->actorId, $command->playerId, $command->teamId);
        }

        $competition->removePlayerFromTeam($teamId, new PlayerId($command->playerId));

        $this->competitions->save($competition);
    }

    private function isAuthorizedToRemove(Competition $competition, TeamId $teamId, string $playerId, string $actorId): bool
    {
        if ($actorId === $playerId) {
            return true;
        }

        if ($actorId === $competition->getTeamCaptainId($teamId)->value) {
            return true;
        }

        return $this->authorization->authorizes($actorId, $competition->getOrganizationId());
    }
}
