<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Model\EncounterResult;
use App\Competition\Domain\Model\Score;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use App\Tests\Support\Builder\CompetitionBuilder;
use App\Tests\Support\Http\AuthenticatedOrganizer;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CorrectEncounterResultControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private InMemoryCompetitionRepository $competitions;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $this->competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $this->competitions);
    }

    #[Test]
    public function it_corrects_the_result(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy($organizer->organizationId)
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withBracketGenerated()
            ->build();
        $encounter = $competition->getBracket()?->getRound(1)->getEncounters()[0];
        self::assertNotNull($encounter);
        $competition->recordResult($encounter->id, EncounterResult::regularTime(Score::of(2, 1)));
        $this->competitions->save($competition);

        $this->client->request('PUT', "/competitions/{$competition->getId()->value}/encounters/{$encounter->id->value}/result", server: [
            'CONTENT_TYPE' => 'application/json',
            ...$organizer->authorizationHeader(),
        ], content: json_encode([
            'regularTime' => ['home' => 3, 'away' => 1],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(204);
        self::assertSame(3, $encounter->getResult()?->regularTime->home);
    }

    #[Test]
    public function it_transmits_every_phase_of_the_corrected_result(): void
    {
        $organizer = AuthenticatedOrganizer::signIn(self::getContainer());

        $competition = CompetitionBuilder::aCompetition()
            ->ownedBy($organizer->organizationId)
            ->withTeam('Team A', captainId: 'captain-a')
            ->withTeam('Team B', captainId: 'captain-b')
            ->withBracketGenerated()
            ->build();
        $encounter = $competition->getBracket()?->getRound(1)->getEncounters()[0];
        self::assertNotNull($encounter);
        $competition->recordResult($encounter->id, EncounterResult::regularTime(Score::of(2, 1)));
        $this->competitions->save($competition);

        $this->client->request('PUT', "/competitions/{$competition->getId()->value}/encounters/{$encounter->id->value}/result", server: [
            'CONTENT_TYPE' => 'application/json',
            ...$organizer->authorizationHeader(),
        ], content: json_encode([
            'regularTime' => ['home' => 1, 'away' => 1],
            'extraTime' => ['home' => 2, 'away' => 2],
            'penalties' => ['home' => 4, 'away' => 3],
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(204);
        $extraTime = $encounter->getResult()?->extraTime;
        $penalties = $encounter->getResult()?->penalties;
        self::assertNotNull($extraTime);
        self::assertNotNull($penalties);
        self::assertSame(2, $extraTime->home);
        self::assertSame(2, $extraTime->away);
        self::assertSame(4, $penalties->home);
        self::assertSame(3, $penalties->away);
    }
}
