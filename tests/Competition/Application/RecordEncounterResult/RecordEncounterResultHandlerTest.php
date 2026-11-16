<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\RecordEncounterResult;

use App\Competition\Application\RecordEncounterResult\RecordEncounterResultCommand;
use App\Competition\Application\RecordEncounterResult\RecordEncounterResultHandler;
use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Service\InMemoryOrganizerOrganizationAuthorization;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecordEncounterResultHandlerTest extends TestCase
{
    #[Test]
    public function it_records_a_regular_time_result(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withBracketGenerated()
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);
        $authorization = new InMemoryOrganizerOrganizationAuthorization();
        $authorization->grantOwnership('organizer-1', $competition->getOrganizationId());
        $bracket = $competition->getBracket();
        self::assertNotNull($bracket);
        $encounter = $bracket->getRound(1)->getEncounters()[0];

        $handler = new RecordEncounterResultHandler($competitions, $authorization);

        $handler(new RecordEncounterResultCommand(
            competitionId: $competition->getId()->value,
            encounterId: $encounter->id->value,
            organizerId: 'organizer-1',
            regularTimeHome: 2,
            regularTimeAway: 1,
        ));

        $result = $encounter->getResult();
        self::assertNotNull($result);
        self::assertSame(2, $result->regularTime->home);
        self::assertSame(1, $result->regularTime->away);
    }

    #[Test]
    public function it_rejects_recording_a_result_for_an_unknown_competition(): void
    {
        $handler = new RecordEncounterResultHandler(new InMemoryCompetitionRepository(), new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RecordEncounterResultCommand(
            competitionId: 'unknown',
            encounterId: 'encounter-1',
            organizerId: 'organizer-1',
            regularTimeHome: 2,
            regularTimeAway: 1,
        ));
    }

    #[Test]
    public function it_rejects_recording_a_result_when_the_organizer_does_not_own_the_organization(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withBracketGenerated()
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new RecordEncounterResultHandler($competitions, new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(OrganizerNotAuthorizedForOrganizationException::class);

        $handler(new RecordEncounterResultCommand(
            competitionId: $competition->getId()->value,
            encounterId: 'encounter-1',
            organizerId: 'organizer-1',
            regularTimeHome: 2,
            regularTimeAway: 1,
        ));
    }
}
