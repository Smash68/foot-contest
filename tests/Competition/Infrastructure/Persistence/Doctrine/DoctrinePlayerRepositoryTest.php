<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Persistence\Doctrine;

use App\Competition\Domain\Model\Player;
use App\Competition\Infrastructure\Persistence\Doctrine\DoctrinePlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrinePlayerRepositoryTest extends KernelTestCase
{
    #[Test]
    public function it_retrieves_a_saved_player_by_its_id(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = new DoctrinePlayerRepository($entityManager);

        $id = $repository->nextIdentity();
        $player = Player::register($id, 'Captain America', 'captain@example.com', 'hashed-password');

        $repository->save($player);
        $entityManager->clear();

        $found = $repository->ofId($id);

        self::assertNotNull($found);
        self::assertTrue($id->equals($found->getId()));
        self::assertSame('captain@example.com', $found->getEmail());
    }

    #[Test]
    public function it_retrieves_a_saved_player_by_its_email(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $repository = new DoctrinePlayerRepository($entityManager);

        $id = $repository->nextIdentity();
        $player = Player::register($id, 'Captain America', 'captain@example.com', 'hashed-password');

        $repository->save($player);
        $entityManager->clear();

        $found = $repository->ofEmail('captain@example.com');

        self::assertNotNull($found);
        self::assertTrue($id->equals($found->getId()));
    }
}
