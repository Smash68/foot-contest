<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter;

use App\Competition\Application\GetEncounter\View\EncounterDetailView;
use App\Competition\Application\GetEncounter\View\EncounterResultView;
use App\Competition\Application\GetEncounter\View\ParticipantDetailView;
use App\Competition\Application\GetEncounter\View\ParticipantViewType;
use App\Competition\Application\GetEncounter\View\PlayerSummaryView;
use App\Competition\Application\GetEncounter\View\ScoreView;
use App\Competition\Application\GetEncounter\View\TeamSummaryView;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\Encounter;
use App\Competition\Domain\Model\EncounterResult;
use App\Competition\Domain\Model\Participant;
use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Score;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Repository\PlayerRepository;

final class EncounterViewAssembler
{
    public function __construct(private PlayerRepository $players)
    {
    }

    public function toView(Competition $competition, Encounter $encounter): EncounterDetailView
    {
        return new EncounterDetailView(
            $encounter->id->value,
            $this->participantToView($competition, $encounter->getHome()),
            $this->participantToView($competition, $encounter->getAway()),
            $this->resultToView($encounter->getResult()),
        );
    }

    private function participantToView(Competition $competition, Participant $participant): ParticipantDetailView
    {
        if ($participant->isTeam()) {
            return new ParticipantDetailView(ParticipantViewType::Team, team: $this->teamToView($competition, $participant->getTeamId()));
        }

        if ($participant->isBye()) {
            return new ParticipantDetailView(ParticipantViewType::Bye);
        }

        return new ParticipantDetailView(ParticipantViewType::Pending, pendingWinnerOfEncounterId: $participant->getPendingWinnerOfEncounterId()->value);
    }

    private function teamToView(Competition $competition, TeamId $teamId): TeamSummaryView
    {
        return new TeamSummaryView(
            $teamId->value,
            $competition->getTeamName($teamId),
            $competition->getTeamCaptainId($teamId)->value,
            array_map($this->playerToView(...), $competition->getTeamRoster($teamId)),
        );
    }

    private function playerToView(PlayerId $playerId): PlayerSummaryView
    {
        $player = $this->players->ofId($playerId);
        assert($player instanceof Player, "Player '{$playerId->value}' from a team roster must be persisted.");

        return new PlayerSummaryView($playerId->value, $player->getName());
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
