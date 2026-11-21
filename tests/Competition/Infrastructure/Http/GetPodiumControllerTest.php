<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GetPodiumControllerTest extends WebTestCase
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
    public function it_returns_the_podium_of_a_finished_competition(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->finished()
            ->build();
        $final = $competition->getBracket()?->getRound(1)->getEncounters()[0];
        self::assertNotNull($final);
        $this->competitions->save($competition);

        $this->client->request('GET', "/competitions/{$competition->getId()->value}/podium");

        self::assertResponseStatusCodeSame(200);
        self::assertSame(
            ['champion' => $final->getWinner()->value, 'runnerUp' => $final->getLoser()->value, 'third' => null],
            json_decode((string) $this->client->getResponse()->getContent(), true),
        );
    }

    #[Test]
    public function it_returns_404_while_the_competition_is_not_finished(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withAllEncountersPlayed()
            ->build();
        $this->competitions->save($competition);

        $this->client->request('GET', "/competitions/{$competition->getId()->value}/podium");

        self::assertResponseStatusCodeSame(404);
    }
}
