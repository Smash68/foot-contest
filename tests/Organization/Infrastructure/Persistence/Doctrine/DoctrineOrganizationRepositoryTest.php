<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Infrastructure\Persistence\Doctrine\DoctrineOrganizationRepository;
use App\Tests\Support\Builder\OrganizationBuilder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineOrganizationRepositoryTest extends KernelTestCase
{
    #[Test]
    public function it_retrieves_a_saved_organization_by_its_id(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineOrganizationRepository($entityManager);

        $organization = OrganizationBuilder::anOrganization()->build();
        $id = $organization->getId();

        $repository->save($organization);
        $entityManager->clear();

        $found = $repository->ofId($id);

        self::assertNotNull($found);
        self::assertTrue($id->equals($found->getId()));
        self::assertSame('Ligue amateur du Nord', $found->getName());
        self::assertTrue((new OrganizerId('organizer-1'))->equals($found->getOwnerId()));
    }
}