<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\GetEncounter;

use App\Competition\Application\GetEncounter\EncounterViewAssembler;
use App\Competition\Application\GetEncounter\GetEncounterHandler;
use App\Competition\Application\GetEncounter\GetEncounterQuery;
use App\Competition\Domain\Model\Competition;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryPlayerRepository;
use App\Tests\Support\Assertion\EncounterSheetAssert;
use App\Tests\Support\Builder\CompetitionBuilder;
use App\Tests\Support\Builder\PlayerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetEncounterHandlerTest extends TestCase
{
    #[Test]
    public function it_returns_the_sheet_of_an_encounter(): void
    {
        $players = new InMemoryPlayerRepository();
        $players->save(PlayerBuilder::aPlayer()->withId('captain-a')->build());
        $players->save(PlayerBuilder::aPlayer()->withId('captain-b')->build());

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withBracketGenerated()
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $encounterId = $this->firstEncounterIdOf($competition);

        $handler = new GetEncounterHandler($competitions, new EncounterViewAssembler($players));

        $view = $handler(new GetEncounterQuery($competition->getId()->value, $encounterId));

        EncounterSheetAssert::assertThat($view)
            ->isForEncounter($encounterId)
            ->opposes('Team A', 'Team B')
            ->listsOnlyTheCaptainInEachTeam()
            ->hasNoResultYet();
    }

    #[Test]
    public function it_rejects_getting_an_encounter_of_an_unknown_competition(): void
    {
        $handler = new GetEncounterHandler(new InMemoryCompetitionRepository(), new EncounterViewAssembler(new InMemoryPlayerRepository()));

        $this->expectException(\InvalidArgumentException::class);

        $handler(new GetEncounterQuery('unknown', 'enc-1'));
    }

    private function firstEncounterIdOf(Competition $competition): string
    {
        $bracket = $competition->getBracket();
        self::assertNotNull($bracket);

        return $bracket->getRounds()[0]->getEncounters()[0]->id->value;
    }
}
