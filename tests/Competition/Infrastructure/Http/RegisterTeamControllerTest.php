<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Http;

use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Infrastructure\Persistence\Doctrine\DoctrinePlayerRepository;
use App\Competition\Infrastructure\Persistence\InMemory\InMemoryCompetitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegisterTeamControllerTest extends WebTestCase
{
    #[Test]
    public function it_registers_a_team(): void
    {
        $client = static::createClient();

        $competitions = new InMemoryCompetitionRepository();
        self::getContainer()->set(CompetitionRepository::class, $competitions);

        $competition = Competition::create($competitions->nextIdentity(), 'Summer Cup', TeamCapacity::of(2, 4));
        $competitions->save($competition);

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        (new DoctrinePlayerRepository($entityManager))->save(
            new Player(new PlayerId('captain@example.com'), 'Captain'),
        );

        $client->request('POST', "/competitions/{$competition->getId()->value}/teams", server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Team A',
            'captainId' => 'captain@example.com',
        ]));

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $payload);
        self::assertNotEmpty($payload['id']);
    }
}
