<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Domain\Model\CheckoutSession;
use App\Organization\Domain\Model\CheckoutSessionStatus;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Infrastructure\Persistence\Doctrine\DoctrineCheckoutSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DoctrineCheckoutSessionRepositoryTest extends KernelTestCase
{
    #[Test]
    public function it_retrieves_a_pending_checkout_session_by_its_checkout_reference(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCheckoutSessionRepository($entityManager);

        $ownerId = new OrganizerId('11111111-1111-1111-1111-111111111111');
        $checkoutReference = new CheckoutReference('checkout_ref_123');
        $id = $repository->nextIdentity();
        $session = CheckoutSession::initiate($id, 'Ligue amateur du Nord', $ownerId, $checkoutReference);

        $repository->save($session);
        $entityManager->clear();

        $found = $repository->ofCheckoutReference($checkoutReference);

        self::assertNotNull($found);
        self::assertTrue($id->equals($found->getId()));
        self::assertSame('Ligue amateur du Nord', $found->getOrganizationName());
        self::assertTrue($ownerId->equals($found->getOwnerId()));
        self::assertTrue($checkoutReference->equals($found->getCheckoutReference()));
        self::assertSame(CheckoutSessionStatus::Pending, $found->getStatus());
    }

    #[Test]
    public function it_persists_a_completed_checkout_session_with_its_organization_id(): void
    {
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        assert($entityManager instanceof EntityManagerInterface);
        $repository = new DoctrineCheckoutSessionRepository($entityManager);

        $ownerId = new OrganizerId('11111111-1111-1111-1111-111111111111');
        $checkoutReference = new CheckoutReference('checkout_ref_456');
        $id = $repository->nextIdentity();
        $session = CheckoutSession::initiate($id, 'Ligue amateur du Nord', $ownerId, $checkoutReference);
        $organizationId = new OrganizationId('22222222-2222-2222-2222-222222222222');
        $session->complete($organizationId);

        $repository->save($session);
        $entityManager->clear();

        $found = $repository->ofCheckoutReference($checkoutReference);

        self::assertNotNull($found);
        self::assertSame(CheckoutSessionStatus::Completed, $found->getStatus());
        self::assertTrue($organizationId->equals($found->getOrganizationId()));
    }
}
