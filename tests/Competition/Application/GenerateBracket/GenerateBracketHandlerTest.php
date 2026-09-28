<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\GenerateBracket;

use App\Competition\Application\GenerateBracket\GenerateBracketCommand;
use App\Competition\Application\GenerateBracket\GenerateBracketHandler;
use App\Competition\Domain\Format\SingleElimination\SingleEliminationBracketGenerator;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Service\BracketGeneratorFactory;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GenerateBracketHandlerTest extends TestCase
{
    #[Test]
    public function it_generates_the_bracket_for_an_eligible_competition(): void
    {
        $competition = CompetitionBuilder::aCompetition()
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withRegistrationClosed()
            ->build();
        $competitions = new InMemoryCompetitionRepository();
        $competitions->save($competition);

        $handler = new GenerateBracketHandler($competitions, $this->bracketGeneratorFactory());

        $handler(new GenerateBracketCommand($competition->getId()->value));

        self::assertNotNull($competition->getBracket());
    }

    #[Test]
    public function it_rejects_generating_the_bracket_for_an_unknown_competition(): void
    {
        $handler = new GenerateBracketHandler(new InMemoryCompetitionRepository(), $this->bracketGeneratorFactory());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new GenerateBracketCommand('unknown'));
    }

    private function bracketGeneratorFactory(): BracketGeneratorFactory
    {
        return new BracketGeneratorFactory([
            CompetitionFormat::SingleElimination->value => new SingleEliminationBracketGenerator(),
        ]);
    }
}
