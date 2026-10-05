<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Repository\PlayerRepository;
use App\Competition\Domain\Service\AccessTokenIssuer;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RequestToJoinTeamControllerTest extends WebTestCase
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
    public function it_records_a_join_request(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->build();
        $this->competitions->save($competition);

        $token = $this->authenticatedPlayer();

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/teams/team-a/join-requests", server: [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_returns_401_without_a_token(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/teams/team-a/join-requests");

        self::assertResponseStatusCodeSame(401);
    }

    private function authenticatedPlayer(): string
    {
        $players = self::getContainer()->get(PlayerRepository::class);
        assert($players instanceof PlayerRepository);
        $playerId = $players->nextIdentity();
        $players->save(Player::register($playerId, 'Applicant', 'applicant@example.com', 'hashed-password'));

        $accessTokenIssuer = self::getContainer()->get(AccessTokenIssuer::class);
        assert($accessTokenIssuer instanceof AccessTokenIssuer);

        return $accessTokenIssuer->issue($playerId);
    }
}
