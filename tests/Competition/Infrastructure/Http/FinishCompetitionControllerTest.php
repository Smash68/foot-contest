<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use App\Tests\Support\Http\AuthenticatedOrganizer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FinishCompetitionControllerTest extends WebTestCase
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
    public function it_finishes_the_competition(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy($organizer->organizationId)
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withAllEncountersPlayed()
            ->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/finish", server: $organizer->authorizationHeader());

        self::assertResponseStatusCodeSame(204);
    }
}
