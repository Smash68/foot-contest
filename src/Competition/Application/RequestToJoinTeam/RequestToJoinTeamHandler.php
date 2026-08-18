<?php

declare(strict_types=1);

namespace App\Competition\Application\RequestToJoinTeam;

use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Repository\PlayerRepository;

final readonly class RequestToJoinTeamHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
        private PlayerRepository $players,
    ) {
    }

    public function __invoke(RequestToJoinTeamCommand $command): void
    {
        $competition = $this->competitions->ofId(new CompetitionId($command->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$command->competitionId}' does not exist.");
        }

        $playerId = new PlayerId($command->playerId);

        if ($this->players->ofId($playerId) === null) {
            throw new \InvalidArgumentException("Player '{$command->playerId}' does not exist.");
        }

        $competition->requestToJoinTeam(new TeamId($command->teamId), $playerId);

        $this->competitions->save($competition);
    }
}
