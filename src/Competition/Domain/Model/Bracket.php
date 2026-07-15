<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

interface Bracket
{
    /** @return Round[] */
    public function getRounds(): array;

    public function countRounds(): int;

    public function countEncounters(): int;

    public function getRound(int $number): Round;

    public function isComplete(): bool;

    public function getChampion(): Team;

    public function recordResult(EncounterId $encounterId, EncounterResult $result): void;
}