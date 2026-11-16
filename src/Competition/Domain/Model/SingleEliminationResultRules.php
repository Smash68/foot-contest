<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final class SingleEliminationResultRules
{
    public static function assertAcceptable(EncounterId $encounterId, EncounterResult $result): void
    {
        if ($result->isDraw()) {
            throw new \InvalidArgumentException("Encounter '{$encounterId->value}': a single elimination encounter cannot end in a draw.");
        }

        if ($result->wentStraightToPenalties()) {
            throw new \InvalidArgumentException("Encounter '{$encounterId->value}': penalties require extra time in a single elimination encounter.");
        }
    }
}
