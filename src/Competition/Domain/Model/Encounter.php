<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final class Encounter
{
    private Participant $home;
    private Participant $away;
    private ?EncounterResult $result = null;

    public function __construct(
        public readonly EncounterId $id,
        Participant $home,
        Participant $away,
    ) {
        $this->home = $home;
        $this->away = $away;
    }

    public function play(EncounterResult $result): void
    {
        if (!$this->home->isTeam() || !$this->away->isTeam()) {
            throw new \LogicException("Encounter '{$this->id->value}': both participants must be known teams.");
        }

        if ($this->result !== null) {
            throw new \LogicException("Encounter '{$this->id->value}' is already completed.");
        }

        $this->result = $result;
    }

    public function correct(EncounterResult $result): void
    {
        if ($this->result === null) {
            throw new \LogicException("Encounter '{$this->id->value}' has not been played yet.");
        }

        $this->result = $result;
    }

    public function wouldChangeWinner(EncounterResult $result): bool
    {
        if ($this->result === null) {
            throw new \LogicException("Encounter '{$this->id->value}' has no result yet.");
        }

        return $this->result->winner() !== $result->winner();
    }

    public function replaceTeam(TeamId $previous, TeamId $replacement): void
    {
        if ($this->home->holdsTeam($previous)) {
            $this->home = Participant::forTeam($replacement);
        } elseif ($this->away->holdsTeam($previous)) {
            $this->away = Participant::forTeam($replacement);
        }
    }

    public function resolveHome(Participant $participant): void
    {
        $this->home = $participant;
    }

    public function resolveAway(Participant $participant): void
    {
        $this->away = $participant;
    }

    public function getHome(): Participant
    {
        return $this->home;
    }

    public function getAway(): Participant
    {
        return $this->away;
    }

    public function getResult(): ?EncounterResult
    {
        return $this->result;
    }

    public function isCompleted(): bool
    {
        return $this->result !== null;
    }

    public function getWinner(): TeamId
    {
        if ($this->result === null) {
            throw new \LogicException("Encounter '{$this->id->value}' has no result yet.");
        }

        $participant = $this->result->winner() === Side::Home ? $this->home : $this->away;

        return $participant->getTeamId();
    }

    public function getLoser(): TeamId
    {
        if ($this->result === null) {
            throw new \LogicException("Encounter '{$this->id->value}' has no result yet.");
        }

        $participant = $this->result->winner() === Side::Home ? $this->away : $this->home;

        return $participant->getTeamId();
    }
}
