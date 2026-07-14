<?php

declare(strict_types=1);

namespace App\Tournament\Domain\Format\SingleElimination;

use App\Tournament\Domain\Model\Bracket;
use App\Tournament\Domain\Model\EncounterId;
use App\Tournament\Domain\Model\Round;
use App\Tournament\Domain\Model\Team;
use App\Tournament\Domain\Service\BracketGenerator;
use InvalidArgumentException;

final class BracketGeneratorWithThirdPlaceMatch implements BracketGenerator
{
    public function __construct(
        private readonly BracketGenerator $inner,
    ) {}

    /** @param Team[] $teams */
    public function generate(array $teams): Bracket
    {
        $bracket = $this->inner->generate($teams);

        if ($bracket->countRounds() < 2) {
            throw new InvalidArgumentException('A third place match requires a semi-final round.');
        }

        $semiFinalRound = $bracket->getRound($bracket->countRounds() - 1);
        $this->assertEligibleForThirdPlaceMatch($semiFinalRound);

        [$semiFinalOne, $semiFinalTwo] = $semiFinalRound->getEncounters();
        $fixture = new ThirdPlaceFixture(
            new EncounterId('third-place-match'),
            $semiFinalOne->id,
            $semiFinalTwo->id,
        );

        return new BracketWithThirdPlaceMatch($bracket, $fixture);
    }

    private function assertEligibleForThirdPlaceMatch(Round $semiFinalRound): void
    {
        $semiFinals = $semiFinalRound->getEncounters();

        if (count($semiFinals) !== 2) {
            throw new InvalidArgumentException('A third place match requires exactly two semi-final encounters.');
        }

        foreach ($semiFinals as $semiFinal) {
            if ($semiFinal->getHome()->isBye() || $semiFinal->getAway()->isBye()) {
                throw new InvalidArgumentException('A third place match cannot be generated when a semi-final involves a bye.');
            }
        }
    }
}