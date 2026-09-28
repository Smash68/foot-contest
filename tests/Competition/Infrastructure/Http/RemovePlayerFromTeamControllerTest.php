<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Repository\PlayerRepository;
use App\Competition\Domain\Service\AccessTokenIssuer as CompetitionAccessTokenIssuer;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Organization\Domain\Repository\OrganizerRepository;
use App\Organization\Domain\Service\AccessTokenIssuer as OrganizationAccessTokenIssuer;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RemovePlayerFromTeamControllerTest extends WebTestCase
{
    #[Test]
    public function it_removes_a_player_who_requests_their_own_departure(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        [$token, $memberId] = $this->authenticatedPlayer();

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->withTeamMember('team-a', $memberId)
            ->build();
        $competitions->save($competition);

        $client->request('DELETE', "/competitions/{$competition->getId()->value}/teams/team-a/players/{$memberId}", server: [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_removes_a_player_when_excluded_by_the_captain(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        [$captainToken, $captainId] = $this->authenticatedPlayer();

        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: $captainId, id: 'team-a')
            ->withTeamMember('team-a', 'member')
            ->build();
        $competitions->save($competition);

        $client->request('DELETE', "/competitions/{$competition->getId()->value}/teams/team-a/players/member", server: [
            'HTTP_AUTHORIZATION' => "Bearer {$captainToken}",
        ]);

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_removes_a_player_when_excluded_by_the_owning_organizer(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        [$organizerToken, $organizationId] = $this->authenticatedOrganizer();

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy($organizationId)
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->withTeamMember('team-a', 'member')
            ->build();
        $competitions->save($competition);

        $client->request('DELETE', "/competitions/{$competition->getId()->value}/teams/team-a/players/member", server: [
            'HTTP_AUTHORIZATION' => "Bearer {$organizerToken}",
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
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->withTeamMember('team-a', 'member')
            ->build();
        $competitions->save($competition);

        $client->request('DELETE', "/competitions/{$competition->getId()->value}/teams/team-a/players/member");

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_returns_403_when_neither_the_player_the_captain_nor_the_owning_organizer(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy('someone-elses-organization')
            ->withTeam('Team A', captainId: 'captain-a', id: 'team-a')
            ->withTeamMember('team-a', 'member')
            ->build();
        $competitions->save($competition);

        [$token] = $this->authenticatedPlayer();

        $client->request('DELETE', "/competitions/{$competition->getId()->value}/teams/team-a/players/member", server: [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function authenticatedPlayer(): array
    {
        $players = self::getContainer()->get(PlayerRepository::class);
        assert($players instanceof PlayerRepository);
        $playerId = $players->nextIdentity();
        $players->save(Player::register($playerId, 'Member', 'member-'.$playerId->value.'@example.com', 'hashed-password'));

        $accessTokenIssuer = self::getContainer()->get(CompetitionAccessTokenIssuer::class);
        assert($accessTokenIssuer instanceof CompetitionAccessTokenIssuer);

        return [$accessTokenIssuer->issue($playerId), $playerId->value];
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

        $accessTokenIssuer = self::getContainer()->get(OrganizationAccessTokenIssuer::class);
        assert($accessTokenIssuer instanceof OrganizationAccessTokenIssuer);
        $token = $accessTokenIssuer->issue($organizerId);

        return [$token, $organizationId->value];
    }
}
