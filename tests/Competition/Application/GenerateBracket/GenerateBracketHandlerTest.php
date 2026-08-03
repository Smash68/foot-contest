<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\GenerateBracket;

use App\Competition\Application\GenerateBracket\GenerateBracketCommand;
use App\Competition\Application\GenerateBracket\GenerateBracketHandler;
use App\Competition\Domain\Format\SingleElimination\SingleEliminationBracketGenerator;
use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Service\BracketGeneratorFactory;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GenerateBracketHandlerTest extends TestCase
{
    #[Test]
    public function it_generates_the_bracket_for_an_eligible_competition(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain-a@example.com')));
        $competition->register(Team::create(new TeamId('t2'), 'Team B', new PlayerId('captain-b@example.com')));
        $competition->closeRegistration();
        $competitions->save($competition);

        $handler = new GenerateBracketHandler($competitions, $this->bracketGeneratorFactory());

        $handler(new GenerateBracketCommand('c1'));

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
