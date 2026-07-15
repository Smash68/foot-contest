<?php

declare(strict_types=1);

namespace App\Tests\Tournament\Domain\Format\SingleElimination;

use App\Tournament\Domain\Format\SingleElimination\BracketGeneratorWithThirdPlaceMatch;
use App\Tournament\Domain\Format\SingleElimination\BracketWithThirdPlaceMatch;
use App\Tournament\Domain\Format\SingleElimination\SingleEliminationBracketGenerator;
use App\Tournament\Domain\Model\EncounterId;
use App\Tournament\Domain\Model\EncounterResult;
use App\Tournament\Domain\Model\Score;
use App\Tournament\Domain\Model\Team;
use App\Tournament\Domain\Model\TeamId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BracketGeneratorWithThirdPlaceMatchTest extends TestCase
{
    private BracketGeneratorWithThirdPlaceMatch $generator;

    protected function setUp(): void
    {
        $this->generator = new BracketGeneratorWithThirdPlaceMatch(new SingleEliminationBracketGenerator());
    }

    #[Test]
    public function it_generates_a_bracket_with_third_place_match_capability(): void
    {
        $bracket = $this->generator->generate($this->makeTeams(4));

        self::assertInstanceOf(BracketWithThirdPlaceMatch::class, $bracket);
        self::assertNull($bracket->getThirdPlaceEncounter());
    }

    #[Test]
    public function third_place_encounter_becomes_available_once_both_semi_finals_are_played(): void
    {
        $bracket = $this->generator->generate($this->makeTeams(4));
        $semiFinals = $bracket->getRound(1)->getEncounters();

        $bracket->recordResult($semiFinals[0]->id, EncounterResult::regularTime(Score::of(2, 0)));
        $bracket->recordResult($semiFinals[1]->id, EncounterResult::regularTime(Score::of(0, 1)));

        $thirdPlaceEncounter = $bracket->getThirdPlaceEncounter();
        self::assertNotNull($thirdPlaceEncounter);
        self::assertTrue($thirdPlaceEncounter->getHome()->isTeam());
        self::assertTrue($thirdPlaceEncounter->getAway()->isTeam());
    }

    #[Test]
    public function it_throws_when_there_is_no_semi_final_round(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->generator->generate($this->makeTeams(2));
    }

    #[Test]
    public function it_throws_when_the_semi_final_round_involves_a_bye(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->generator->generate($this->makeTeams(3));
    }

    // --- helpers ---

    /** @return Team[] */
    private function makeTeams(int $count): array
    {
        return array_map(fn(int $i) => new Team(new TeamId("t{$i}"), "Team {$i}"), range(1, $count));
    }
}