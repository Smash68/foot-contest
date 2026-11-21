<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\FinishCompetition;

use App\Competition\Application\FinishCompetition\FinishCompetitionCommand;
use App\Competition\Application\FinishCompetition\FinishCompetitionHandler;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
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

        $handler = new FinishCompetitionHandler($competitions);

        $handler(new FinishCompetitionCommand($competition->getId()->value, 'organizer-1'));

        self::assertTrue($competition->isFinished());
    }

    #[Test]
    public function it_rejects_finishing_an_unknown_competition(): void
    {
        $handler = new FinishCompetitionHandler(new InMemoryCompetitionRepository());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new FinishCompetitionCommand('unknown', 'organizer-1'));
    }
}
