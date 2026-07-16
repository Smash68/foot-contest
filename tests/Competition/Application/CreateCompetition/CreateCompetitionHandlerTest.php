<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CreateCompetition;

use App\Competition\Application\CreateCompetition\CreateCompetitionCommand;
use App\Competition\Application\CreateCompetition\CreateCompetitionHandler;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateCompetitionHandlerTest extends TestCase
{
    #[Test]
    public function it_persists_a_new_competition(): void
    {
        $repository = new InMemoryCompetitionRepository();
        $handler = new CreateCompetitionHandler($repository);

        ($handler)(new CreateCompetitionCommand('c1', 'Summer Cup', 2, 4));

        $competition = $repository->ofId(new CompetitionId('c1'));

        self::assertNotNull($competition);
        self::assertTrue($competition->isOpenForRegistration());
        self::assertSame(0, $competition->countRegistrations());
    }
}