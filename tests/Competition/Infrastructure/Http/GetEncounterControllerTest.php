<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Format\SingleElimination\SingleEliminationBracketGenerator;
use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Repository\PlayerRepository;
use App\Competition\Domain\Service\BracketGeneratorFactory;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GetEncounterControllerTest extends WebTestCase
{
    #[Test]
    public function it_returns_the_sheet_of_an_encounter(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        $players = self::getContainer()->get(PlayerRepository::class);
        assert($players instanceof PlayerRepository);
        $players->save(Player::register(new PlayerId('captain-a'), 'Alice', 'alice@example.com', 'hashed'));
        $players->save(Player::register(new PlayerId('captain-b'), 'Bob', 'bob@example.com', 'hashed'));

        $competition = Competition::create($competitions->nextIdentity(), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
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

        $client->request('GET', "/competitions/{$competition->getId()->value}/encounters/{$encounterId}");

        self::assertResponseStatusCodeSame(200);

        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertSame($encounterId, $data['id']);
        self::assertSame('team', $data['home']['type']);
        self::assertNotNull($data['home']['team']);
        self::assertSame('team', $data['away']['type']);
        self::assertNotNull($data['away']['team']);
        self::assertEqualsCanonicalizing(['Team A', 'Team B'], [$data['home']['team']['name'], $data['away']['team']['name']]);
        self::assertCount(1, $data['home']['team']['players']);
        self::assertNull($data['result']);
    }

    #[Test]
    public function it_returns_404_when_the_encounter_does_not_exist_in_the_bracket(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        $players = self::getContainer()->get(PlayerRepository::class);
        assert($players instanceof PlayerRepository);
        $players->save(Player::register(new PlayerId('captain-a'), 'Alice', 'alice@example.com', 'hashed'));
        $players->save(Player::register(new PlayerId('captain-b'), 'Bob', 'bob@example.com', 'hashed'));

        $competition = Competition::create($competitions->nextIdentity(), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain-a')));
        $competition->register(Team::create(new TeamId('t2'), 'Team B', new PlayerId('captain-b')));
        $competition->closeRegistration();
        $competition->generateBracket(new BracketGeneratorFactory([
            CompetitionFormat::SingleElimination->value => new SingleEliminationBracketGenerator(),
        ]));
        $competitions->save($competition);

        $client->request('GET', "/competitions/{$competition->getId()->value}/encounters/unknown-encounter");

        self::assertResponseStatusCodeSame(404);
    }
}
