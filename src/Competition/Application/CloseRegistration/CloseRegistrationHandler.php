<?php

declare(strict_types=1);

namespace App\Competition\Application\CloseRegistration;

use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Repository\CompetitionRepository;

final readonly class CloseRegistrationHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
    ) {
    }

    public function __invoke(CloseRegistrationCommand $command): void
    {
        $competition = $this->competitions->ofId(new CompetitionId($command->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$command->competitionId}' does not exist.");
        }

        $competition->closeRegistration();

        $this->competitions->save($competition);
    }
}
