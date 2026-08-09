<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CloseRegistration;

use App\Competition\Application\CloseRegistration\CloseRegistrationCommand;
use App\Competition\Application\CloseRegistration\CloseRegistrationHandler;
use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Service\OrganizerOrganizationAuthorization;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CloseRegistrationHandlerTest extends TestCase
{
    #[Test]
    public function it_closes_registration_for_an_eligible_competition(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain-a@example.com')));
        $competition->register(Team::create(new TeamId('t2'), 'Team B', new PlayerId('captain-b@example.com')));
        $competitions->save($competition);

        $handler = new CloseRegistrationHandler($competitions, $this->authorizationStub(true));

        $handler(new CloseRegistrationCommand('c1', 'organizer-1'));

        self::assertFalse($competition->isOpenForRegistration());
    }

    #[Test]
    public function it_rejects_closing_an_unknown_competition(): void
    {
        $handler = new CloseRegistrationHandler(new InMemoryCompetitionRepository(), $this->authorizationStub(true));

        $this->expectException(\InvalidArgumentException::class);

        $handler(new CloseRegistrationCommand('unknown', 'organizer-1'));
    }

    #[Test]
    public function it_rejects_closing_when_the_organizer_does_not_own_the_organization(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain-a@example.com')));
        $competition->register(Team::create(new TeamId('t2'), 'Team B', new PlayerId('captain-b@example.com')));
        $competitions->save($competition);

        $handler = new CloseRegistrationHandler($competitions, $this->authorizationStub(false));

        $this->expectException(OrganizerNotAuthorizedForOrganizationException::class);

        $handler(new CloseRegistrationCommand('c1', 'organizer-1'));
    }

    private function authorizationStub(bool $authorized): OrganizerOrganizationAuthorization
    {
        $authorization = $this->createStub(OrganizerOrganizationAuthorization::class);
        $authorization->method('authorizes')->willReturn($authorized);

        return $authorization;
    }
}
