<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\GetPodium;

use App\Competition\Application\GetPodium\GetPodiumHandler;
use App\Competition\Application\GetPodium\GetPodiumQuery;
use App\Competition\Application\GetPodium\View\PodiumView;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetPodiumHandlerTest extends TestCase
{
    #[Test]
    public function it_returns_the_podium_of_a_finished_competition(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->finished()
            ->build();
        $final = $competition->getBracket()?->getRound(1)->getEncounters()[0];
        self::assertNotNull($final);
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new GetPodiumHandler($competitions);

        $view = $handler(new GetPodiumQuery($competition->getId()->value));

        self::assertEquals(new PodiumView(champion: $final->getWinner()->value, runnerUp: $final->getLoser()->value, third: null), $view);
    }

    #[Test]
    public function it_returns_no_podium_while_the_competition_is_not_finished(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withAllEncountersPlayed()
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new GetPodiumHandler($competitions);

        self::assertNull($handler(new GetPodiumQuery($competition->getId()->value)));
    }
}
