<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CreateCompetition;

use App\Competition\Application\CreateCompetition\CreateCompetitionCommand;
use App\Competition\Application\CreateCompetition\CreateCompetitionHandler;
use App\Competition\Domain\Exception\IncompleteTeamsException;
use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Service\InMemoryOrganizerOrganizationAuthorization;
use App\Tests\Support\Assertion\CompetitionAssert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateCompetitionHandlerTest extends TestCase
{
    #[Test]
    public function it_persists_the_organization_from_the_command(): void
    {
        $repository = new InMemoryCompetitionRepository();
        $authorization = new InMemoryOrganizerOrganizationAuthorization();
        $authorization->grantOwnership('organizer-1', new OrganizationId('org-1'));
        $handler = new CreateCompetitionHandler($repository, $authorization);

        $id = ($handler)(new CreateCompetitionCommand('Summer Cup', 2, 4, CompetitionFormat::SingleElimination->value, false, 1, 'organizer-1', 'org-1'));

        $competition = $repository->ofId($id);

        self::assertNotNull($competition);
        self::assertEquals(new OrganizationId('org-1'), $competition->getOrganizationId());
    }

    #[Test]
    public function it_rejects_creation_when_the_organizer_does_not_own_the_organization(): void
    {
        $repository = new InMemoryCompetitionRepository();
        $handler = new CreateCompetitionHandler($repository, new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(OrganizerNotAuthorizedForOrganizationException::class);

        $handler(new CreateCompetitionCommand('Summer Cup', 2, 4, CompetitionFormat::SingleElimination->value, false, 1, 'organizer-1', 'org-1'));
    }

    #[Test]
    public function it_persists_a_new_competition(): void
    {
        $repository = new InMemoryCompetitionRepository();
        $authorization = new InMemoryOrganizerOrganizationAuthorization();
        $authorization->grantOwnership('organizer-1', new OrganizationId('org-1'));
        $handler = new CreateCompetitionHandler($repository, $authorization);

        $id = ($handler)(new CreateCompetitionCommand('Summer Cup', 2, 4, CompetitionFormat::SingleElimination->value, false, 1, 'organizer-1', 'org-1'));

        $competition = $repository->ofId($id);

        self::assertNotNull($competition);
        self::assertTrue($competition->isOpenForRegistration());
        CompetitionAssert::assertThat($competition)->hasRegisteredTeamsCount(0);
    }

    #[Test]
    public function it_persists_the_requested_format_and_third_place_option(): void
    {
        $repository = new InMemoryCompetitionRepository();
        $authorization = new InMemoryOrganizerOrganizationAuthorization();
        $authorization->grantOwnership('organizer-1', new OrganizationId('org-1'));
        $handler = new CreateCompetitionHandler($repository, $authorization);

        $id = ($handler)(new CreateCompetitionCommand('Summer Cup', 2, 4, CompetitionFormat::SingleElimination->value, true, 1, 'organizer-1', 'org-1'));

        $competition = $repository->ofId($id);

        self::assertNotNull($competition);
        self::assertSame(CompetitionFormat::SingleElimination, $competition->getFormat());
        self::assertTrue($competition->includesThirdPlaceMatch());
    }

    #[Test]
    public function it_persists_the_requested_minimum_roster_size(): void
    {
        $repository = new InMemoryCompetitionRepository();
        $authorization = new InMemoryOrganizerOrganizationAuthorization();
        $authorization->grantOwnership('organizer-1', new OrganizationId('org-1'));
        $handler = new CreateCompetitionHandler($repository, $authorization);

        $id = ($handler)(new CreateCompetitionCommand('Summer Cup', 2, 4, CompetitionFormat::SingleElimination->value, false, 2, 'organizer-1', 'org-1'));

        $competition = $repository->ofId($id);
        self::assertNotNull($competition);
        $competition->register(Team::create(new TeamId('team-a'), 'Team A', new PlayerId('captain-a')));
        $competition->register(Team::create(new TeamId('team-b'), 'Team B', new PlayerId('captain-b')));

        $this->expectException(IncompleteTeamsException::class);

        $competition->closeRegistration();
    }
}
