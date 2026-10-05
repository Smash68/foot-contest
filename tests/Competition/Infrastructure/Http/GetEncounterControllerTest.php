<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Repository\PlayerRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use App\Tests\Support\Builder\PlayerBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GetEncounterControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private InMemoryCompetitionRepository $competitions;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $this->competitions);
    }

    #[Test]
    public function it_returns_the_sheet_of_an_encounter(): void
    {
        $players = self::getContainer()->get(PlayerRepository::class);
        assert($players instanceof PlayerRepository);
        $players->save(PlayerBuilder::aPlayer()->withId('captain-a')->build());
        $players->save(PlayerBuilder::aPlayer()->withId('captain-b')->build());

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withBracketGenerated()
            ->build();
        $this->competitions->save($competition);

        $bracket = $competition->getBracket();
        self::assertNotNull($bracket);
        $encounterId = $bracket->getRounds()[0]->getEncounters()[0]->id->value;

        $this->client->request('GET', "/competitions/{$competition->getId()->value}/encounters/{$encounterId}");

        self::assertResponseStatusCodeSame(200);

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertArrayHasKey('id', $data);
        self::assertArrayHasKey('home', $data);
        self::assertArrayHasKey('away', $data);
        self::assertArrayHasKey('result', $data);
    }

    #[Test]
    public function it_returns_404_when_the_encounter_does_not_exist_in_the_bracket(): void
    {
        $players = self::getContainer()->get(PlayerRepository::class);
        assert($players instanceof PlayerRepository);
        $players->save(PlayerBuilder::aPlayer()->withId('captain-a')->build());
        $players->save(PlayerBuilder::aPlayer()->withId('captain-b')->build());

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withBracketGenerated()
            ->build();
        $this->competitions->save($competition);

        $this->client->request('GET', "/competitions/{$competition->getId()->value}/encounters/unknown-encounter");

        self::assertResponseStatusCodeSame(404);
    }
}
