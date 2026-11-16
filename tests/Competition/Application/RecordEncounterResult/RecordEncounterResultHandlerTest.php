<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\RecordEncounterResult;

use App\Competition\Application\RecordEncounterResult\RecordEncounterResultCommand;
use App\Competition\Application\RecordEncounterResult\RecordEncounterResultHandler;
use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\Encounter;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Service\InMemoryOrganizerOrganizationAuthorization;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecordEncounterResultHandlerTest extends TestCase
{
    private Competition $competition;
    private Encounter $encounter;
    private RecordEncounterResultHandler $handler;

    protected function setUp(): void
    {
        $this->competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withBracketGenerated()
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($this->competition);
        $authorization = new InMemoryOrganizerOrganizationAuthorization();
        $authorization->grantOwnership('organizer-1', $this->competition->getOrganizationId());
        $bracket = $this->competition->getBracket();
        self::assertNotNull($bracket);
        $this->encounter = $bracket->getRound(1)->getEncounters()[0];

        $this->handler = new RecordEncounterResultHandler($competitions, $authorization);
    }

    #[Test]
    public function it_records_a_regular_time_result(): void
    {
        ($this->handler)(new RecordEncounterResultCommand(
            competitionId: $this->competition->getId()->value,
            encounterId: $this->encounter->id->value,
            organizerId: 'organizer-1',
            regularTimeHome: 2,
            regularTimeAway: 1,
        ));

        $result = $this->encounter->getResult();
        self::assertNotNull($result);
        self::assertSame(2, $result->regularTime->home);
        self::assertSame(1, $result->regularTime->away);
    }

    #[Test]
    public function it_records_a_result_after_extra_time(): void
    {
        ($this->handler)(new RecordEncounterResultCommand(
            competitionId: $this->competition->getId()->value,
            encounterId: $this->encounter->id->value,
            organizerId: 'organizer-1',
            regularTimeHome: 1,
            regularTimeAway: 1,
            extraTimeHome: 2,
            extraTimeAway: 1,
        ));

        $extraTime = $this->encounter->getResult()?->extraTime;
        self::assertNotNull($extraTime);
        self::assertSame(2, $extraTime->home);
        self::assertSame(1, $extraTime->away);
    }

    #[Test]
    public function it_records_a_result_after_penalties(): void
    {
        ($this->handler)(new RecordEncounterResultCommand(
            competitionId: $this->competition->getId()->value,
            encounterId: $this->encounter->id->value,
            organizerId: 'organizer-1',
            regularTimeHome: 1,
            regularTimeAway: 1,
            extraTimeHome: 2,
            extraTimeAway: 2,
            penaltiesHome: 4,
            penaltiesAway: 3,
        ));

        $penalties = $this->encounter->getResult()?->penalties;
        self::assertNotNull($penalties);
        self::assertSame(4, $penalties->home);
        self::assertSame(3, $penalties->away);
    }

    #[Test]
    public function it_rejects_recording_a_result_for_an_unknown_competition(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)(new RecordEncounterResultCommand(
            competitionId: 'unknown',
            encounterId: $this->encounter->id->value,
            organizerId: 'organizer-1',
            regularTimeHome: 2,
            regularTimeAway: 1,
        ));
    }

    #[Test]
    public function it_rejects_recording_a_result_when_the_organizer_does_not_own_the_organization(): void
    {
        $this->expectException(OrganizerNotAuthorizedForOrganizationException::class);

        ($this->handler)(new RecordEncounterResultCommand(
            competitionId: $this->competition->getId()->value,
            encounterId: $this->encounter->id->value,
            organizerId: 'organizer-2',
            regularTimeHome: 2,
            regularTimeAway: 1,
        ));
    }
}
