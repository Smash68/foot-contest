<?php

declare(strict_types=1);

namespace App\Competition\Application\RegisterTeam;

use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Repository\PlayerRepository;
use App\Competition\Domain\Repository\TeamRepository;

final readonly class RegisterTeamHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
        private PlayerRepository $players,
        private TeamRepository $teams,
    ) {
    }

    public function __invoke(RegisterTeamCommand $command): void
    {
        $competition = $this->competitions->ofId(new CompetitionId($command->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$command->competitionId}' does not exist.");
        }

        $captainId = new PlayerId($command->captainId);

        if ($this->players->ofId($captainId) === null) {
            throw new \InvalidArgumentException("Player '{$command->captainId}' does not exist.");
        }

        $team = Team::create($this->teams->nextIdentity(), $command->teamName, $captainId);

        $competition->register($team);

        $this->competitions->save($competition);
    }
}
