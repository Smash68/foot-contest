<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\GetEncounter;

use App\Competition\Application\GetEncounter\EncounterViewAssembler;
use App\Competition\Application\GetEncounter\GetEncounterHandler;
use App\Competition\Application\GetEncounter\GetEncounterQuery;
use App\Competition\Application\GetEncounter\View\ParticipantViewType;
use App\Competition\Domain\Format\SingleElimination\SingleEliminationBracketGenerator;
use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Service\BracketGeneratorFactory;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryPlayerRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetEncounterHandlerTest extends TestCase
{
    #[Test]
    public function it_returns_the_sheet_of_an_encounter(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $players = new InMemoryPlayerRepository();
        $players->save(Player::register(new PlayerId('captain-a'), 'Alice', 'alice@example.com', 'hashed'));
        $players->save(Player::register(new PlayerId('captain-b'), 'Bob', 'bob@example.com', 'hashed'));

        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain-a')));
        $competition->register(Team::create(new TeamId('t2'), 'Team B', new PlayerId('captain-b')));
        $competition->closeRegistration();
        $competition->generateBracket(new BracketGeneratorFactory([
            CompetitionFormat::SingleElimination->value => new SingleEliminationBracketGenerator(),
        ]));
        $competitions->save($competition);

        $bracket = $competition->getBracket();
        self::assertNotNull($bracket);
        $encounterId = $bracket->getRounds()[0]->getEncounters()[0]->id->value;

        $handler = new GetEncounterHandler($competitions, new EncounterViewAssembler($players));

        $view = $handler(new GetEncounterQuery('c1', $encounterId));

        self::assertNotNull($view);
        self::assertSame($encounterId, $view->id);

        self::assertSame(ParticipantViewType::Team, $view->home->type);
        self::assertNotNull($view->home->team);
        self::assertSame(ParticipantViewType::Team, $view->away->type);
        self::assertNotNull($view->away->team);

        $teamNames = [$view->home->team->name, $view->away->team->name];
        self::assertEqualsCanonicalizing(['Team A', 'Team B'], $teamNames);
        self::assertCount(1, $view->home->team->players);
        self::assertSame($view->home->team->captainId, $view->home->team->players[0]->id);

        self::assertNull($view->result);
    }

    #[Test]
    public function it_rejects_getting_an_encounter_of_an_unknown_competition(): void
    {
        $handler = new GetEncounterHandler(new InMemoryCompetitionRepository(), new EncounterViewAssembler(new InMemoryPlayerRepository()));

        $this->expectException(\InvalidArgumentException::class);

        $handler(new GetEncounterQuery('unknown', 'enc-1'));
    }
}
