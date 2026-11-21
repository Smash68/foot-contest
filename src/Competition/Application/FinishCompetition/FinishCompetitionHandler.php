<?php

declare(strict_types=1);

namespace App\Competition\Application\FinishCompetition;

use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Repository\CompetitionRepository;

final readonly class FinishCompetitionHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
    ) {
    }

    public function __invoke(FinishCompetitionCommand $command): void
    {
        $competition = $this->competitions->ofId(new CompetitionId($command->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$command->competitionId}' does not exist.");
        }

        $competition->finish();

        $this->competitions->save($competition);
    }
}
