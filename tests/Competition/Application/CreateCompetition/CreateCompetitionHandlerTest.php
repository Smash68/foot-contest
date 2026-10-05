<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CreateCompetition;

use App\Competition\Application\CreateCompetition\CreateCompetitionCommand;
use App\Competition\Application\CreateCompetition\CreateCompetitionHandler;
use App\Competition\Domain\Exception\IncompleteTeamsException;
use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Domain\Model\Competition;
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
    private InMemoryCompetitionRepository $repository;
    private CreateCompetitionHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryCompetitionRepository();
        $authorization = new InMemoryOrganizerOrganizationAuthorization();
        $authorization->grantOwnership('organizer-1', new OrganizationId('org-1'));
        $this->handler = new CreateCompetitionHandler($this->repository, $authorization);
    }

    #[Test]
    public function it_persists_the_organization_from_the_command(): void
    {
        $competition = $this->createCompetition($this->aCommand(organizationId: 'org-1'));

        self::assertEquals(new OrganizationId('org-1'), $competition->getOrganizationId());
    }

    #[Test]
    public function it_rejects_creation_when_the_organizer_does_not_own_the_organization(): void
    {
        $this->expectException(OrganizerNotAuthorizedForOrganizationException::class);

        ($this->handler)($this->aCommand(organizationId: 'someone-elses-organization'));
    }

    #[Test]
    public function it_persists_a_new_competition(): void
    {
        $competition = $this->createCompetition($this->aCommand());

        self::assertTrue($competition->isOpenForRegistration());
        CompetitionAssert::assertThat($competition)->hasRegisteredTeamsCount(0);
    }

    #[Test]
    public function it_persists_the_requested_format_and_third_place_option(): void
    {
        $competition = $this->createCompetition($this->aCommand(format: CompetitionFormat::SingleElimination->value, includeThirdPlaceMatch: true));

        self::assertSame(CompetitionFormat::SingleElimination, $competition->getFormat());
        self::assertTrue($competition->includesThirdPlaceMatch());
    }

    #[Test]
    public function it_persists_the_requested_minimum_roster_size(): void
    {
        $competition = $this->createCompetition($this->aCommand(minRosterSize: 2));
        $competition->register(Team::create(new TeamId('team-a'), 'Team A', new PlayerId('captain-a')));
        $competition->register(Team::create(new TeamId('team-b'), 'Team B', new PlayerId('captain-b')));

        $this->expectException(IncompleteTeamsException::class);

        $competition->closeRegistration();
    }

    private function aCommand(
        string $format = CompetitionFormat::SingleElimination->value,
        bool $includeThirdPlaceMatch = false,
        int $minRosterSize = 1,
        string $organizationId = 'org-1',
    ): CreateCompetitionCommand {
        return new CreateCompetitionCommand('Summer Cup', 2, 4, $format, $includeThirdPlaceMatch, $minRosterSize, 'organizer-1', $organizationId);
    }

    private function createCompetition(CreateCompetitionCommand $command): Competition
    {
        $competition = $this->repository->ofId(($this->handler)($command));
        self::assertNotNull($competition);

        return $competition;
    }
}
