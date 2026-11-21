<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\FinishCompetition;

use App\Competition\Application\FinishCompetition\FinishCompetitionCommand;
use App\Competition\Application\FinishCompetition\FinishCompetitionHandler;
use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Service\InMemoryOrganizerOrganizationAuthorization;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FinishCompetitionHandlerTest extends TestCase
{
    #[Test]
    public function it_finishes_a_competition_whose_encounters_are_all_played(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withAllEncountersPlayed()
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);
        $authorization = new InMemoryOrganizerOrganizationAuthorization();
        $authorization->grantOwnership('organizer-1', $competition->getOrganizationId());

        $handler = new FinishCompetitionHandler($competitions, $authorization);

        $handler(new FinishCompetitionCommand($competition->getId()->value, 'organizer-1'));

        self::assertTrue($competition->isFinished());
    }

    #[Test]
    public function it_rejects_finishing_an_unknown_competition(): void
    {
        $handler = new FinishCompetitionHandler(new InMemoryCompetitionRepository(), new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new FinishCompetitionCommand('unknown', 'organizer-1'));
    }

    #[Test]
    public function it_rejects_finishing_when_the_organizer_does_not_own_the_organization(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withAllEncountersPlayed()
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new FinishCompetitionHandler($competitions, new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(OrganizerNotAuthorizedForOrganizationException::class);

        $handler(new FinishCompetitionCommand($competition->getId()->value, 'organizer-1'));
    }
}
