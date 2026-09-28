<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CloseRegistration;

use App\Competition\Application\CloseRegistration\CloseRegistrationCommand;
use App\Competition\Application\CloseRegistration\CloseRegistrationHandler;
use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Service\InMemoryOrganizerOrganizationAuthorization;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CloseRegistrationHandlerTest extends TestCase
{
    #[Test]
    public function it_closes_registration_for_an_eligible_competition(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);
        $authorization = new InMemoryOrganizerOrganizationAuthorization();
        $authorization->grantOwnership('organizer-1', $competition->getOrganizationId());

        $handler = new CloseRegistrationHandler($competitions, $authorization);

        $handler(new CloseRegistrationCommand($competition->getId()->value, 'organizer-1'));

        self::assertFalse($competition->isOpenForRegistration());
    }

    #[Test]
    public function it_rejects_closing_an_unknown_competition(): void
    {
        $handler = new CloseRegistrationHandler(new InMemoryCompetitionRepository(), new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new CloseRegistrationCommand('unknown', 'organizer-1'));
    }

    #[Test]
    public function it_rejects_closing_when_the_organizer_does_not_own_the_organization(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new CloseRegistrationHandler($competitions, new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(OrganizerNotAuthorizedForOrganizationException::class);

        $handler(new CloseRegistrationCommand($competition->getId()->value, 'organizer-1'));
    }
}
