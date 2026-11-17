<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CorrectEncounterResult;

use App\Competition\Application\CorrectEncounterResult\CorrectEncounterResultCommand;
use App\Competition\Application\CorrectEncounterResult\CorrectEncounterResultHandler;
use App\Competition\Application\CorrectEncounterResult\ScoreInput;
use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Domain\Model\EncounterResult;
use App\Competition\Domain\Model\Score;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Competition\Infrastructure\Service\InMemoryOrganizerOrganizationAuthorization;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CorrectEncounterResultHandlerTest extends TestCase
{
    #[Test]
    public function it_corrects_a_regular_time_result(): void
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
        $encounter = $competition->getBracket()?->getRound(1)->getEncounters()[0];
        self::assertNotNull($encounter);
        $competition->recordResult($encounter->id, EncounterResult::regularTime(Score::of(2, 1)));

        $handler = new CorrectEncounterResultHandler($competitions, $authorization);

        $handler(new CorrectEncounterResultCommand(
            competitionId: $competition->getId()->value,
            encounterId: $encounter->id->value,
            organizerId: 'organizer-1',
            regularTime: new ScoreInput(3, 1),
        ));

        $result = $encounter->getResult();
        self::assertNotNull($result);
        self::assertSame(3, $result->regularTime->home);
        self::assertSame(1, $result->regularTime->away);
    }

    #[Test]
    public function it_rejects_correcting_a_result_for_an_unknown_competition(): void
    {
        $handler = new CorrectEncounterResultHandler(new InMemoryCompetitionRepository(), new InMemoryOrganizerOrganizationAuthorization());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new CorrectEncounterResultCommand(
            competitionId: 'unknown',
            encounterId: 'encounter-1',
            organizerId: 'organizer-1',
            regularTime: new ScoreInput(2, 1),
        ));
    }

    #[Test]
    public function it_rejects_correcting_a_result_when_the_organizer_does_not_own_the_organization(): void
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

        $handler = new CorrectEncounterResultHandler($competitions, $authorization);

        $this->expectException(OrganizerNotAuthorizedForOrganizationException::class);

        $handler(new CorrectEncounterResultCommand(
            competitionId: $competition->getId()->value,
            encounterId: 'encounter-1',
            organizerId: 'organizer-2',
            regularTime: new ScoreInput(2, 1),
        ));
    }
}
