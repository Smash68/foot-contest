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
        return EncounterResult::of(
            self::score($command->regularTime),
            $command->extraTime !== null ? self::score($command->extraTime) : null,
            $command->penalties !== null ? self::score($command->penalties) : null,
        );
    }

    private static function score(ScoreInput $input): Score
    {
        return Score::of($input->home, $input->away);
    }
}
