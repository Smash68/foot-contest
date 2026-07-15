<?php

declare(strict_types=1);

namespace App\Tests\Competition\Domain;

use App\Competition\Domain\Format\SingleElimination\SingleEliminationBracketGenerator;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SingleEliminationBracketGeneratorTest extends TestCase
{
    private SingleEliminationBracketGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SingleEliminationBracketGenerator();
    }

    #[Test]
    public function it_requires_at_least_two_teams(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->generator->generate([$this->team('A')]);
    }

    #[Test]
    #[DataProvider('teamCountProvider')]
    public function it_generates_correct_number_of_rounds_and_encounters(
        int $teamCount,
        int $expectedRounds,
        int $expectedTotalEncounters,
    ): void {
        $teams = $this->makeTeams($teamCount);

        $bracket = $this->generator->generate($teams);

        self::assertSame($expectedRounds, $bracket->countRounds());
        self::assertSame($expectedTotalEncounters, $bracket->countEncounters());
    }

    public static function teamCountProvider(): array
    {
        return [
            '2 teams'  => [2, 1, 1],
            '3 teams'  => [3, 2, 3],
            '4 teams'  => [4, 2, 3],
            '5 teams'  => [5, 3, 7],
            '6 teams'  => [6, 3, 7],
            '8 teams'  => [8, 3, 7],
            '16 teams' => [16, 4, 15],
        ];
    }

    #[Test]
    #[DataProvider('byeCountProvider')]
    public function it_assigns_correct_number_of_byes_in_round_one(
        int $teamCount,
        int $expectedRound1Encounters,
    ): void {
        $teams = $this->makeTeams($teamCount);

        $bracket = $this->generator->generate($teams);

        self::assertSame($expectedRound1Encounters, $bracket->getRound(1)->countEncounters());
    }

    public static function byeCountProvider(): array
    {
        return [
            '2 teams → 1 R1 encounter, 0 byes'  => [2, 1],
            '3 teams → 2 R1 encounters, 1 bye'   => [3, 2],
            '4 teams → 2 R1 encounters, 0 byes'  => [4, 2],
            '5 teams → 4 R1 encounters, 3 byes'  => [5, 4],
            '6 teams → 4 R1 encounters, 2 byes'  => [6, 4],
            '8 teams → 4 R1 encounters, 0 byes'  => [8, 4],
        ];
    }

    #[Test]
    public function round_one_slots_are_all_concrete_teams(): void
    {
        $bracket = $this->generator->generate($this->makeTeams(4));

        foreach ($bracket->getRound(1)->getEncounters() as $encounter) {
            self::assertTrue($encounter->getHome()->isTeam());
            self::assertTrue($encounter->getAway()->isTeam());
        }
    }

    #[Test]
    public function subsequent_rounds_reference_winners_of_previous_encounters(): void
    {
        $bracket = $this->generator->generate($this->makeTeams(4));

        // Round 2 (the final) must reference winners of round 1 encounters
        $round2Encounter = $bracket->getRound(2)->getEncounters()[0];

        self::assertTrue($round2Encounter->getHome()->isPending());
        self::assertTrue($round2Encounter->getAway()->isPending());
    }

    #[Test]
    public function bye_in_round_one_is_resolved_immediately_in_round_two(): void
    {
        // 3 teams: 1 bye encounter + 1 real encounter in R1
        // → the team with a bye is already known in R2 (not pending)
        // → the winner of the real encounter is still pending in R2
        $bracket = $this->generator->generate($this->makeTeams(3));

        $round2Encounter = $bracket->getRound(2)->getEncounters()[0];

        self::assertTrue($round2Encounter->getHome()->isTeam());
        self::assertTrue($round2Encounter->getAway()->isPending());
    }

    #[Test]
    public function total_encounters_always_equals_next_power_of_two_minus_one(): void
    {
        foreach ([2, 3, 4, 5, 6, 7, 8, 9, 12, 16] as $count) {
            $bracket = $this->generator->generate($this->makeTeams($count));
            $expectedEncounters = 2 ** (int) ceil(log($count, 2)) - 1;

            self::assertSame(
                $expectedEncounters,
                $bracket->countEncounters(),
                "Failed for $count teams",
            );
        }
    }

    #[Test]
    public function all_round_one_teams_are_from_the_provided_list(): void
    {
        $teams = $this->makeTeams(4);
        $teamIds = array_map(fn(Team $t) => $t->getId(), $teams);

        $bracket = $this->generator->generate($teams);

        foreach ($bracket->getRound(1)->getEncounters() as $encounter) {
            self::assertContains($encounter->getHome()->getTeam()->getId(), $teamIds);
            self::assertContains($encounter->getAway()->getTeam()->getId(), $teamIds);
        }
    }

    #[Test]
    public function each_team_appears_exactly_once_in_round_one(): void
    {
        $teams = $this->makeTeams(8);

        $bracket = $this->generator->generate($teams);

        $seenIds = [];
        foreach ($bracket->getRound(1)->getEncounters() as $encounter) {
            $seenIds[] = $encounter->getHome()->getTeam()->getId()->value;
            $seenIds[] = $encounter->getAway()->getTeam()->getId()->value;
        }

        self::assertCount(count($seenIds), array_unique($seenIds), 'Duplicate team in round 1');
    }

    // --- helpers ---

    /** @return Team[] */
    private function makeTeams(int $count): array
    {
        return array_map(fn(int $i) => $this->team("Team-{$i}"), range(1, $count));
    }

    private function team(string $name): Team
    {
        return new Team(new TeamId($name), $name);
    }
}