<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\GetBracket;

use App\Competition\Application\GetBracket\BracketViewAssembler;
use App\Competition\Application\GetBracket\GetBracketHandler;
use App\Competition\Application\GetBracket\GetBracketQuery;
use App\Competition\Application\GetBracket\View\ParticipantViewType;
use App\Competition\Domain\Format\SingleElimination\SingleEliminationBracketGenerator;
use App\Competition\Domain\Model\BracketConfiguration;
use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Service\BracketGeneratorFactory;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetBracketHandlerTest extends TestCase
{
    #[Test]
    public function it_returns_the_bracket_of_a_competition_with_a_generated_bracket(): void
    {
        $competitions = new InMemoryCompetitionRepository();
        $competition = Competition::create(new CompetitionId('c1'), 'Summer Cup', TeamCapacity::of(2, 4), new BracketConfiguration(CompetitionFormat::SingleElimination, false), new OrganizationId('org-1'));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain-a@example.com')));
        $competition->register(Team::create(new TeamId('t2'), 'Team B', new PlayerId('captain-b@example.com')));
        $competition->closeRegistration();
        $competition->generateBracket(new BracketGeneratorFactory([
            CompetitionFormat::SingleElimination->value => new SingleEliminationBracketGenerator(),
        ]));
        $competitions->save($competition);

        $handler = new GetBracketHandler($competitions, new BracketViewAssembler());

        $view = $handler(new GetBracketQuery('c1'));

        self::assertNotNull($view);
        self::assertCount(1, $view->rounds);
        $round = $view->rounds[0];
        self::assertSame(1, $round->number);
        self::assertCount(1, $round->encounters);
        $encounter = $round->encounters[0];
        self::assertSame(ParticipantViewType::Team, $encounter->home->type);
        self::assertSame(ParticipantViewType::Team, $encounter->away->type);
        self::assertEqualsCanonicalizing(['t1', 't2'], [$encounter->home->teamId, $encounter->away->teamId]);
        self::assertNull($encounter->result);
        self::assertFalse($view->isComplete);
        self::assertNull($view->champion);
    }

    #[Test]
    public function it_rejects_getting_the_bracket_of_an_unknown_competition(): void
    {
        $handler = new GetBracketHandler(new InMemoryCompetitionRepository(), new BracketViewAssembler());

        $this->expectException(\InvalidArgumentException::class);

        $handler(new GetBracketQuery('unknown'));
    }
}
