<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket;

use App\Competition\Application\GetBracket\View\BracketView;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Repository\CompetitionRepository;

final readonly class GetBracketHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
        private BracketViewAssembler $assembler,
    ) {
    }

    public function __invoke(GetBracketQuery $query): ?BracketView
    {
        $competition = $this->competitions->ofId(new CompetitionId($query->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$query->competitionId}' does not exist.");
        }

        $bracket = $competition->getBracket();

        return $bracket === null ? null : $this->assembler->toView($bracket);
    }
}