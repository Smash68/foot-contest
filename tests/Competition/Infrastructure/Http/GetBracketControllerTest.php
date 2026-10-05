<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GetBracketControllerTest extends WebTestCase
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
    public function it_returns_the_bracket_of_a_competition_with_a_generated_bracket(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withBracketGenerated()
            ->build();
        $this->competitions->save($competition);

        $this->client->request('GET', "/competitions/{$competition->getId()->value}/bracket");

        self::assertResponseStatusCodeSame(200);

        $data = json_decode((string) $this->client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertArrayHasKey('rounds', $data);
        self::assertArrayHasKey('isComplete', $data);
        self::assertArrayHasKey('champion', $data);
    }

    #[Test]
    public function it_returns_404_when_the_bracket_is_not_generated_yet(): void
    {
        $competition = CompetitionBuilder::aCompetition()->build();
        $this->competitions->save($competition);

        $this->client->request('GET', "/competitions/{$competition->getId()->value}/bracket");

        self::assertResponseStatusCodeSame(404);
    }
}
