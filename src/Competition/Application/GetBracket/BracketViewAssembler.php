<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket;

use App\Competition\Application\GetBracket\View\BracketView;
use App\Competition\Application\GetBracket\View\EncounterResultView;
use App\Competition\Application\GetBracket\View\EncounterView;
use App\Competition\Application\GetBracket\View\ParticipantView;
use App\Competition\Application\GetBracket\View\ParticipantViewType;
use App\Competition\Application\GetBracket\View\RoundView;
use App\Competition\Application\GetBracket\View\ScoreView;
use App\Competition\Domain\Model\Bracket;
use App\Competition\Domain\Model\Encounter;
use App\Competition\Domain\Model\EncounterResult;
use App\Competition\Domain\Model\Participant;
use App\Competition\Domain\Model\Round;
use App\Competition\Domain\Model\Score;

final class BracketViewAssembler
{
    public function toView(Bracket $bracket): BracketView
    {
        $isComplete = $bracket->isComplete();

        return new BracketView(
            array_map($this->roundToView(...), $bracket->getRounds()),
            $isComplete,
            $isComplete ? $bracket->getChampion()->value : null,
        );
    }

    private function roundToView(Round $round): RoundView
    {
        return new RoundView($round->getNumber(), array_map($this->encounterToView(...), $round->getEncounters()));
    }

    private function encounterToView(Encounter $encounter): EncounterView
    {
        return new EncounterView(
            $encounter->id->value,
            $this->participantToView($encounter->getHome()),
            $this->participantToView($encounter->getAway()),
            $this->resultToView($encounter->getResult()),
        );
    }

    private function participantToView(Participant $participant): ParticipantView
    {
        if ($participant->isTeam()) {
            return new ParticipantView(ParticipantViewType::Team, teamId: $participant->getTeamId()->value);
        }

        if ($participant->isBye()) {
            return new ParticipantView(ParticipantViewType::Bye);
        }

        return new ParticipantView(ParticipantViewType::Pending, pendingWinnerOfEncounterId: $participant->getPendingWinnerOfEncounterId()->value);
    }

    private function resultToView(?EncounterResult $result): ?EncounterResultView
    {
        if ($result === null) {
            return null;
        }

        return new EncounterResultView(
            $this->scoreToView($result->regularTime),
            $result->extraTime === null ? null : $this->scoreToView($result->extraTime),
            $result->penalties === null ? null : $this->scoreToView($result->penalties),
        );
    }

    private function scoreToView(Score $score): ScoreView
    {
        return new ScoreView($score->home, $score->away);
    }
}
