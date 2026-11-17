<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CorrectEncounterResult;

use App\Competition\Application\CorrectEncounterResult\CorrectEncounterResultCommand;
use App\Competition\Application\CorrectEncounterResult\CorrectEncounterResultHandler;
use App\Competition\Application\CorrectEncounterResult\ScoreInput;
use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\Encounter;
use App\Competition\Domain\Model\EncounterResult;
use App\Competition\Domain\Model\Score;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Service\InMemoryOrganizerOrganizationAuthorization;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CorrectEncounterResultHandlerTest extends TestCase
{
    private Competition $competition;
    private Encounter $encounter;
    private CorrectEncounterResultHandler $handler;

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
        $encounter = $this->competition->getBracket()?->getRound(1)->getEncounters()[0];
        self::assertNotNull($encounter);
        $this->encounter = $encounter;
        $this->competition->recordResult($this->encounter->id, EncounterResult::regularTime(Score::of(2, 1)));

        $this->handler = new CorrectEncounterResultHandler($competitions, $authorization);
    }

    #[Test]
    public function it_corrects_a_regular_time_result(): void
    {
        ($this->handler)(new CorrectEncounterResultCommand(
            competitionId: $this->competition->getId()->value,
            encounterId: $this->encounter->id->value,
            organizerId: 'organizer-1',
            regularTime: new ScoreInput(3, 1),
        ));

        $result = $this->encounter->getResult();
        self::assertNotNull($result);
        self::assertSame(3, $result->regularTime->home);
        self::assertSame(1, $result->regularTime->away);
    }

    #[Test]
    public function it_corrects_a_result_after_penalties(): void
    {
        ($this->handler)(new CorrectEncounterResultCommand(
            competitionId: $this->competition->getId()->value,
            encounterId: $this->encounter->id->value,
            organizerId: 'organizer-1',
            regularTime: new ScoreInput(1, 1),
            extraTime: new ScoreInput(2, 2),
            penalties: new ScoreInput(4, 3),
        ));

        $penalties = $this->encounter->getResult()?->penalties;
        self::assertNotNull($penalties);
        self::assertSame(4, $penalties->home);
        self::assertSame(3, $penalties->away);
    }

    #[Test]
    public function it_rejects_correcting_a_result_for_an_unknown_competition(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ($this->handler)(new CorrectEncounterResultCommand(
            competitionId: 'unknown',
            encounterId: $this->encounter->id->value,
            organizerId: 'organizer-1',
            regularTime: new ScoreInput(3, 1),
        ));
    }

    #[Test]
    public function it_rejects_correcting_a_result_when_the_organizer_does_not_own_the_organization(): void
    {
        $this->expectException(OrganizerNotAuthorizedForOrganizationException::class);

        ($this->handler)(new CorrectEncounterResultCommand(
            competitionId: $this->competition->getId()->value,
            encounterId: $this->encounter->id->value,
            organizerId: 'organizer-2',
            regularTime: new ScoreInput(3, 1),
        ));
    }
}
