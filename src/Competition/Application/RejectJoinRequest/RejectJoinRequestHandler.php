<?php

declare(strict_types=1);

namespace App\Competition\Application\RejectJoinRequest;

use App\Competition\Domain\Exception\NotAuthorizedToManageJoinRequestException;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Repository\CompetitionRepository;

final readonly class RejectJoinRequestHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
    ) {
    }

    public function __invoke(RejectJoinRequestCommand $command): void
    {
        $competition = $this->competitions->ofId(new CompetitionId($command->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$command->competitionId}' does not exist.");
        }

        $teamId = new TeamId($command->teamId);

        if (!$competition->getTeamCaptainId($teamId)->equals(new PlayerId($command->actorId))) {
            throw new NotAuthorizedToManageJoinRequestException($command->actorId, $command->teamId);
        }

        $competition->rejectJoinRequest($teamId, new PlayerId($command->playerId));

        $this->competitions->save($competition);
    }
}
