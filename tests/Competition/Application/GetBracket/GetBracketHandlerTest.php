<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\GetBracket;

use App\Competition\Application\GetBracket\BracketViewAssembler;
use App\Competition\Application\GetBracket\GetBracketHandler;
use App\Competition\Application\GetBracket\GetBracketQuery;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Assertion\BracketViewAssert;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetBracketHandlerTest extends TestCase
{
    #[Test]
    public function it_returns_the_bracket_of_a_competition_with_a_generated_bracket(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->withTeam('Team B', captainId: 'captain-b', id: 'team-b')
            ->withBracketGenerated()
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new GetBracketHandler($competitions, new BracketViewAssembler());

        $view = $handler(new GetBracketQuery($competition->getId()->value));

        self::assertNotNull($view);
        BracketViewAssert::assertThat($view)
            ->hasRoundsCount(1)
            ->roundHasEncountersCount(roundNumber: 1, count: 1)
            ->encounterOpposes(roundNumber: 1, encounterIndex: 0, teamIdA: 'team-a', teamIdB: 'team-b')
            ->encounterHasNoResult(roundNumber: 1, encounterIndex: 0)
            ->isNotComplete()
            ->hasNoChampion();
    }

    #[Test]
    public function it_rejects_getting_the_bracket_of_an_unknown_competition(): void
    {
        $handler = new GetBracketHandler(new InMemoryCompetitionRepository(), new BracketViewAssembler());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new GetBracketQuery('unknown'));
    }
}
