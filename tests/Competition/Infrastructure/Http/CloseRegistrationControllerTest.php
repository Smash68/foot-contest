<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Repository\OrganizationRepository;
use App\Organization\Domain\Repository\OrganizerRepository;
use App\Organization\Domain\Service\AccessTokenIssuer;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CloseRegistrationControllerTest extends WebTestCase
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
    public function it_closes_registration(): void
    {
        [$token, $organizationId] = $this->authenticatedOrganizer();

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy($organizationId)
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/close-registration", server: [
            'HTTP_AUTHORIZATION' => "Bearer {$token}",
        ]);

        self::assertResponseStatusCodeSame(204);
    }

    #[Test]
    public function it_returns_401_without_a_token(): void
    {
        $competition = CompetitionBuilder::aCompetition()->build();
        $this->competitions->save($competition);

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/close-registration");

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function it_returns_403_when_the_organizer_does_not_own_the_organization(): void
    {
        $competition = CompetitionBuilder::aCompetition()->ownedBy('someone-elses-organization')->build();
        $this->competitions->save($competition);

        [$token] = $this->authenticatedOrganizer();

        $this->client->request('POST', "/competitions/{$competition->getId()->value}/close-registration", server: [
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
