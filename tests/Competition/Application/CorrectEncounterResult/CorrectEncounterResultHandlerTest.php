<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CorrectEncounterResult;

use App\Competition\Application\CorrectEncounterResult\CorrectEncounterResultCommand;
use App\Competition\Application\CorrectEncounterResult\CorrectEncounterResultHandler;
use App\Competition\Application\CorrectEncounterResult\ScoreInput;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CorrectEncounterResultHandlerTest extends TestCase
{
    #[Test]
    public function it_rejects_correcting_a_result_for_an_unknown_competition(): void
    {
        $handler = new CorrectEncounterResultHandler(new InMemoryCompetitionRepository());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new CorrectEncounterResultCommand(
            competitionId: 'unknown',
            encounterId: 'encounter-1',
            organizerId: 'organizer-1',
            regularTime: new ScoreInput(2, 1),
        ));
    }
}
