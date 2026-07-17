<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Persistence\Doctrine;

use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\TeamCapacity;
use App\Competition\Infrastructure\Persistence\Doctrine\DoctrineCompetitionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineCompetitionRepositoryTest extends KernelTestCase
{
    #[Test]
    public function it_retrieves_a_saved_competition_by_its_id(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = new DoctrineCompetitionRepository($entityManager);

        $id = $repository->nextIdentity();
        $competition = Competition::create($id, 'Summer Cup', TeamCapacity::of(2, 4));

        $repository->save($competition);
        $entityManager->clear();

        $found = $repository->ofId($id);

        self::assertNotNull($found);
        self::assertTrue($id->equals($found->getId()));
    }
}