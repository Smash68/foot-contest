<?php

declare(strict_types=1);

namespace App\Tests\Competition\E2E;

use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Infrastructure\Persistence\Doctrine\DoctrineCompetitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CreateCompetitionEndToEndTest extends WebTestCase
{
    #[Test]
    public function it_creates_a_competition_and_persists_it_through_the_real_stack(): void
    {
        $client = static::createClient();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $doctrineRepository = new DoctrineCompetitionRepository($entityManager);
        self::getContainer()->set(CompetitionRepository::class, $doctrineRepository);

        $client->request('POST', '/competitions', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'name' => 'Summer Cup',
            'minTeams' => 2,
            'maxTeams' => 4,
        ]));

        self::assertResponseStatusCodeSame(201);

        $payload = json_decode($client->getResponse()->getContent(), true);
        $competition = $doctrineRepository->ofId(new CompetitionId($payload['id']));

        self::assertNotNull($competition);
        self::assertSame('Summer Cup', $competition->getName());
    }
}
