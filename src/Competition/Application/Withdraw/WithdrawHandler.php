<?php

declare(strict_types=1);

namespace App\Competition\Application\Withdraw;

use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Repository\CompetitionRepository;

final readonly class WithdrawHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
    ) {
    }

    public function __invoke(WithdrawCommand $command): void
    {
        $competition = $this->competitions->ofId(new CompetitionId($command->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$command->competitionId}' does not exist.");
        }

        $competition->withdraw(new TeamId($command->teamId));

        $this->competitions->save($competition);
    }
}