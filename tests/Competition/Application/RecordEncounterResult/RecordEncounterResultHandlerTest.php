<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\RecordEncounterResult;

use App\Competition\Application\RecordEncounterResult\RecordEncounterResultCommand;
use App\Competition\Application\RecordEncounterResult\RecordEncounterResultHandler;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecordEncounterResultHandlerTest extends TestCase
{
    #[Test]
    public function it_rejects_recording_a_result_for_an_unknown_competition(): void
    {
        $handler = new RecordEncounterResultHandler(new InMemoryCompetitionRepository());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new RecordEncounterResultCommand(
            competitionId: 'unknown',
            encounterId: 'encounter-1',
            organizerId: 'organizer-1',
            regularTimeHome: 2,
            regularTimeAway: 1,
        ));
    }
}
