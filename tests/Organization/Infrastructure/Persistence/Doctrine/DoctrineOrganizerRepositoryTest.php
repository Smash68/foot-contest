<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Infrastructure\Persistence\Doctrine\DoctrineOrganizerRepository;
use App\Tests\Support\Builder\OrganizerBuilder;
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

        $organizer = OrganizerBuilder::anOrganizer()->withEmail('organizer@example.com')->build();
        $id = $organizer->getId();

        $repository->save($organizer);
        $entityManager->clear();

        $found = $repository->ofEmail('organizer@example.com');

        self::assertNotNull($found);
        self::assertTrue($id->equals($found->getId()));
        self::assertSame('organizer@example.com', $found->getEmail());
        self::assertSame('hashed-password', $found->getHashedPassword());
    }
}
