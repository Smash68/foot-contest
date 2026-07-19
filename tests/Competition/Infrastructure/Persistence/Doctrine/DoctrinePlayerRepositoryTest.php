<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Persistence\Doctrine;

use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;
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

        $id = new PlayerId('captain@example.com');
        $player = new Player($id, 'Captain America');

        $repository->save($player);
        $entityManager->clear();

        $found = $repository->ofId($id);

        self::assertNotNull($found);
        self::assertTrue($id->equals($found->getId()));
    }
}
