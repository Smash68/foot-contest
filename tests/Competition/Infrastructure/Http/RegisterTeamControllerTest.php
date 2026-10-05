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

final class RegisterTeamControllerTest extends WebTestCase
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
    public function it_registers_a_team(): void
    {
        $competition = CompetitionBuilder::aCompetition()->build();
        $this->competitions->save($competition);

        $token = $this->authenticatedPlayer();

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/teams", server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ], content: json_encode([
            'name' => 'Team A',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('id', $payload);
        self::assertNotEmpty($payload['id']);
    }

    #[Test]
    public function it_returns_409_when_the_team_name_is_already_taken(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'existing-captain')
            ->build();
        $this->competitions->save($competition);

        $token = $this->authenticatedPlayer();

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/teams", server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ], content: json_encode([
            'name' => 'Team A',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function it_returns_401_without_a_token(): void
    {
        $competition = CompetitionBuilder::aCompetition()->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/teams", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Team A',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(401);
    }

    private function authenticatedPlayer(): string
    {
        $players = self::getContainer()->get(PlayerRepository::class);
        assert($players instanceof PlayerRepository);
        $playerId = $players->nextIdentity();
        $players->save(Player::register($playerId, 'Captain', 'captain@example.com', 'hashed-password'));

        $accessTokenIssuer = self::getContainer()->get(AccessTokenIssuer::class);
        assert($accessTokenIssuer instanceof AccessTokenIssuer);

        return $accessTokenIssuer->issue($playerId);
    }
}
