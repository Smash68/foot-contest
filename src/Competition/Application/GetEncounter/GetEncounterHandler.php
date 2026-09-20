<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter;

use App\Competition\Application\GetEncounter\View\EncounterDetailView;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\EncounterId;
use App\Competition\Domain\Repository\CompetitionRepository;

final readonly class GetEncounterHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
        private EncounterViewAssembler $assembler,
    ) {
    }

    public function __invoke(GetEncounterQuery $query): ?EncounterDetailView
    {
        $competition = $this->competitions->ofId(new CompetitionId($query->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$query->competitionId}' does not exist.");
        }

        $bracket = $competition->getBracket();

        if ($bracket === null) {
            return null;
        }

        $encounter = $bracket->findEncounterById(new EncounterId($query->encounterId));

        return $encounter === null ? null : $this->assembler->toView($competition, $encounter);
    }
}
