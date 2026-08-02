<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CloseRegistrationControllerTest extends WebTestCase
{
    #[Test]
    public function it_closes_registration(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        $competition = Competition::create($competitions->nextIdentity(), 'Summer Cup', TeamCapacity::of(2, 4));
        $competition->register(Team::create(new TeamId('t1'), 'Team A', new PlayerId('captain-a@example.com')));
        $competition->register(Team::create(new TeamId('t2'), 'Team B', new PlayerId('captain-b@example.com')));
        $competitions->save($competition);

        $client->request('POST', "/competitions/{$competition->getId()->value}/close-registration");

        self::assertResponseStatusCodeSame(204);
    }
}
