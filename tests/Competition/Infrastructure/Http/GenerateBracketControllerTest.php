<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Repository\PlayerRepository;
use App\Competition\Domain\Service\AccessTokenIssuer as PlayerAccessTokenIssuer;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use App\Tests\Support\Http\AuthenticatedOrganizer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GenerateBracketControllerTest extends WebTestCase
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
    public function it_generates_the_bracket(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy($organizer->organizationId)
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/generate-bracket", server: $organizer->authorizationHeader());

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_returns_401_without_a_token(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/generate-bracket");

        self::assertResponseStatusCodeSame(401);
        self::assertNull($competition->getBracket());
    }

    #[Test]
    public function it_returns_403_when_the_organizer_does_not_own_the_organization(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy('someone-elses-organization')
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $this->competitions->save($competition);

        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/generate-bracket", server: $organizer->authorizationHeader());

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_returns_401_for_a_captain_of_the_competition(): void
    {
        [$token, $captainId] = $this->authenticatedPlayer();

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: $captainId)
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/generate-bracket", server: [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertNull($competition->getBracket());
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function authenticatedPlayer(): array
    {
        $players = self::getContainer()->get(PlayerRepository::class);
        assert($players instanceof PlayerRepository);
        $playerId = $players->nextIdentity();
        $players->save(Player::register($playerId, 'Captain', 'captain@example.com', 'hashed-password'));

        $accessTokenIssuer = self::getContainer()->get(PlayerAccessTokenIssuer::class);
        assert($accessTokenIssuer instanceof PlayerAccessTokenIssuer);

        return [$accessTokenIssuer->issue($playerId), $playerId->value];
    }
}
