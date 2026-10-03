<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Repository\PlayerRepository;
use App\Competition\Domain\Service\AccessTokenIssuer as PlayerAccessTokenIssuer;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Organization\Domain\Repository\OrganizerRepository;
use App\Organization\Domain\Service\AccessTokenIssuer;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GenerateBracketControllerTest extends WebTestCase
{
    #[Test]
    public function it_generates_the_bracket(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        [$token, $organizationId] = $this->authenticatedOrganizer();

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy($organizationId)
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $competitions->save($competition);

        $client->request('POST', "/competitions/{$competition->getId()->value}/generate-bracket", server: [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_returns_401_without_a_token(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $competitions->save($competition);

        $client->request('POST', "/competitions/{$competition->getId()->value}/generate-bracket");

        self::assertResponseStatusCodeSame(401);
        self::assertNull($competition->getBracket());
    }

    #[Test]
    public function it_returns_403_when_the_organizer_does_not_own_the_organization(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy('someone-elses-organization')
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $competitions->save($competition);

        [$token] = $this->authenticatedOrganizer();

        $client->request('POST', "/competitions/{$competition->getId()->value}/generate-bracket", server: [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function it_returns_401_for_a_captain_of_the_competition(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        [$token, $captainId] = $this->authenticatedPlayer();

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: $captainId)
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $competitions->save($competition);

        $client->request('POST', "/competitions/{$competition->getId()->value}/generate-bracket", server: [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        self::assertResponseStatusCodeSame(401);
        self::assertNull($competition->getBracket());
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function authenticatedOrganizer(): array
    {
        $organizers = self::getContainer()->get(OrganizerRepository::class);
        assert($organizers instanceof OrganizerRepository);
        $organizerId = $organizers->nextIdentity();
        $organizers->save(Organizer::register($organizerId, 'organizer@example.com', 'hashed-password'));

        $organizations = self::getContainer()->get(OrganizationRepository::class);
        assert($organizations instanceof OrganizationRepository);
        $organizationId = $organizations->nextIdentity();
        $organizations->save(Organization::create($organizationId, 'Ligue amateur du Nord', $organizerId));

        $accessTokenIssuer = self::getContainer()->get(AccessTokenIssuer::class);
        assert($accessTokenIssuer instanceof AccessTokenIssuer);
        $token = $accessTokenIssuer->issue($organizerId);

        return [$token, $organizationId->value];
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
