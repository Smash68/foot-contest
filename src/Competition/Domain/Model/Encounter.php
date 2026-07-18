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
        if ($this->home->isPending() || $this->away->isPending()) {
            throw new \LogicException("Encounter '{$this->id->value}': participants are not yet determined.");
        }

        if ($this->result !== null) {
            throw new \LogicException("Encounter '{$this->id->value}' is already completed.");
        }

        $this->result = $result;
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

    public function getWinner(): Team
    {
        if ($this->result === null) {
            throw new \LogicException("Encounter '{$this->id->value}' has no result yet.");
        }

        $participant = $this->result->winner() === Side::Home ? $this->home : $this->away;

        return $participant->getTeam();
    }

    public function getLoser(): Team
    {
        if ($this->result === null) {
            throw new \LogicException("Encounter '{$this->id->value}' has no result yet.");
        }

        $participant = $this->result->winner() === Side::Home ? $this->away : $this->home;

        return $participant->getTeam();
    }
}
