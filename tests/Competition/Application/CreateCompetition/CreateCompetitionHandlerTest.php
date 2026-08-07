<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CreateCompetition;

use App\Competition\Application\CreateCompetition\CreateCompetitionCommand;
use App\Competition\Application\CreateCompetition\CreateCompetitionHandler;
use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Service\OrganizerOrganizationAuthorization;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateCompetitionHandlerTest extends TestCase
{
    #[Test]
    public function it_persists_the_organization_from_the_command(): void
    {
        $repository = new InMemoryCompetitionRepository();
        $handler = new CreateCompetitionHandler($repository, $this->authorizationStub(true));

        $id = ($handler)(new CreateCompetitionCommand('Summer Cup', 2, 4, CompetitionFormat::SingleElimination->value, false, 'organizer-1', 'org-1'));

        $competition = $repository->ofId($id);

        self::assertNotNull($competition);
        self::assertTrue($competition->getOrganizationId()->equals(new OrganizationId('org-1')));
    }

    #[Test]
    public function it_rejects_creation_when_the_organizer_does_not_own_the_organization(): void
    {
        $repository = new InMemoryCompetitionRepository();
        $handler = new CreateCompetitionHandler($repository, $this->authorizationStub(false));

        $this->expectException(OrganizerNotAuthorizedForOrganizationException::class);

        $handler(new CreateCompetitionCommand('Summer Cup', 2, 4, CompetitionFormat::SingleElimination->value, false, 'organizer-1', 'org-1'));
    }

    private function authorizationStub(bool $authorized): OrganizerOrganizationAuthorization
    {
        $authorization = $this->createStub(OrganizerOrganizationAuthorization::class);
        $authorization->method('authorizes')->willReturn($authorized);

        return $authorization;
    }

    #[Test]
    public function it_persists_a_new_competition(): void
    {
        $repository = new InMemoryCompetitionRepository();
        $handler = new CreateCompetitionHandler($repository, $this->authorizationStub(true));

        $id = ($handler)(new CreateCompetitionCommand('Summer Cup', 2, 4, CompetitionFormat::SingleElimination->value, false, 'organizer-1', 'org-1'));

        $competition = $repository->ofId($id);

        self::assertNotNull($competition);
        self::assertTrue($competition->isOpenForRegistration());
        self::assertSame(0, $competition->countRegistrations());
    }

    #[Test]
    public function it_persists_the_requested_format_and_third_place_option(): void
    {
        $repository = new InMemoryCompetitionRepository();
        $handler = new CreateCompetitionHandler($repository, $this->authorizationStub(true));

        $id = ($handler)(new CreateCompetitionCommand('Summer Cup', 2, 4, CompetitionFormat::SingleElimination->value, true, 'organizer-1', 'org-1'));

        $competition = $repository->ofId($id);

        self::assertNotNull($competition);
        self::assertSame(CompetitionFormat::SingleElimination, $competition->getFormat());
        self::assertTrue($competition->includesThirdPlaceMatch());
    }
}
