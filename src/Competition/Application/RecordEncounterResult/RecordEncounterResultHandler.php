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
        $regularTime = self::score($command->regularTime);

        if ($command->extraTime !== null) {
            $extraTime = self::score($command->extraTime);

            if ($command->penalties !== null) {
                return EncounterResult::afterPenalties($regularTime, $extraTime, self::score($command->penalties));
            }

            return EncounterResult::afterExtraTime($regularTime, $extraTime);
        }

        return EncounterResult::regularTime($regularTime);
    }

    private static function score(ScoreInput $input): Score
    {
        return Score::of($input->home, $input->away);
    }
}
