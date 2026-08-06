<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Domain\Model\Organizer;
use App\Organization\Infrastructure\Persistence\Doctrine\DoctrineOrganizerRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineOrganizerRepositoryTest extends KernelTestCase
{
    #[Test]
    public function it_retrieves_a_saved_organizer_by_its_email(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineOrganizerRepository($entityManager);

        $id = $repository->nextIdentity();
        $organizer = Organizer::register($id, 'organizer@example.com', 'hashed-password');

        $repository->save($organizer);
        $entityManager->clear();

        $found = $repository->ofEmail('organizer@example.com');

        self::assertNotNull($found);
        self::assertTrue($id->equals($found->getId()));
        self::assertSame('organizer@example.com', $found->getEmail());
        self::assertSame('hashed-password', $found->getHashedPassword());
    }
}
