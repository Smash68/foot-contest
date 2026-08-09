<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Organization\Domain\Repository\OrganizerRepository;
use App\Organization\Domain\Service\AccessTokenIssuer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CloseRegistrationControllerTest extends WebTestCase
{
    #[Test]
    public function it_closes_registration(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        [$token, $organizationId] = $this->authenticatedOrganizer();

        $competition = Competition::create($competitions->nextIdentity(), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId($organizationId));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain-a@example.com')));
        $competition->register(Team::create(new TeamId('t2'), 'Team B', new PlayerId('captain-b@example.com')));
        $competitions->save($competition);

        $client->request('POST', "/competitions/{$competition->getId()->value}/close-registration", server: [
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

        $competition = Competition::create($competitions->nextIdentity(), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competitions->save($competition);

        $client->request('POST', "/competitions/{$competition->getId()->value}/close-registration");

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_returns_403_when_the_organizer_does_not_own_the_organization(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        $competition = Competition::create($competitions->nextIdentity(), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('someone-elses-organization'));
        $competitions->save($competition);

        [$token] = $this->authenticatedOrganizer();

        $client->request('POST', "/competitions/{$competition->getId()->value}/close-registration", server: [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        self::assertResponseStatusCodeSame(403);
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
}
