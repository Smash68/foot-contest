<?php

declare(strict_types=1);

namespace App\Competition\Application\RecordEncounterResult;

use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\EncounterId;
use App\Competition\Domain\Model\EncounterResult;
use App\Competition\Domain\Model\Score;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Service\OrganizerOrganizationAuthorization;

final readonly class RecordEncounterResultHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
        private OrganizerOrganizationAuthorization $authorization,
    ) {
    }

    public function __invoke(RecordEncounterResultCommand $command): void
    {
        $competition = $this->competitions->ofId(new CompetitionId($command->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$command->competitionId}' does not exist.");
        }

        if (!$this->authorization->authorizes($command->organizerId, $competition->getOrganizationId())) {
            throw new OrganizerNotAuthorizedForOrganizationException($command->organizerId, $competition->getOrganizationId()->value);
        }

        $competition->recordResult(new EncounterId($command->encounterId), $this->result($command));

        $this->competitions->save($competition);
    }

    private function result(RecordEncounterResultCommand $command): EncounterResult
    {
        $regularTime = Score::of($command->regularTimeHome, $command->regularTimeAway);

        if ($command->extraTimeHome !== null && $command->extraTimeAway !== null) {
            $extraTime = Score::of($command->extraTimeHome, $command->extraTimeAway);

            if ($command->penaltiesHome !== null && $command->penaltiesAway !== null) {
                return EncounterResult::afterPenalties($regularTime, $extraTime, Score::of($command->penaltiesHome, $command->penaltiesAway));
            }

            return EncounterResult::afterExtraTime($regularTime, $extraTime);
        }

        return EncounterResult::regularTime($regularTime);
    }
}
