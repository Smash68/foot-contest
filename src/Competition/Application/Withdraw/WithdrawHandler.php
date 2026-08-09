<?php

declare(strict_types=1);

namespace App\Competition\Application\Withdraw;

use App\Competition\Domain\Exception\NotAuthorizedToWithdrawException;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Service\OrganizerOrganizationAuthorization;

final readonly class WithdrawHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
        private OrganizerOrganizationAuthorization $authorization,
    ) {
    }

    public function __invoke(WithdrawCommand $command): void
    {
        $competition = $this->competitions->ofId(new CompetitionId($command->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$command->competitionId}' does not exist.");
        }

        $teamId = new TeamId($command->teamId);

        if (!$this->isAuthorizedToWithdraw($competition, $teamId, $command->actorId)) {
            throw new NotAuthorizedToWithdrawException($command->actorId, $command->teamId);
        }

        $competition->withdraw($teamId);

        $this->competitions->save($competition);
    }

    private function isAuthorizedToWithdraw(Competition $competition, TeamId $teamId, string $actorId): bool
    {
        $isCaptain = $competition->getTeamCaptainId($teamId)->equals(new PlayerId($actorId));

        return $isCaptain || $this->authorization->authorizes($actorId, $competition->getOrganizationId());
    }
}
