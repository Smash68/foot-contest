<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\Doctrine;

use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Domain\Model\CheckoutSession;
use App\Organization\Domain\Model\CheckoutSessionId;
use App\Organization\Domain\Repository\CheckoutSessionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineCheckoutSessionRepository implements CheckoutSessionRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function nextIdentity(): CheckoutSessionId
    {
        return new CheckoutSessionId(Uuid::v7()->toRfc4122());
    }

    public function save(CheckoutSession $session): void
    {
        $this->entityManager->persist($session);
        $this->entityManager->flush();
    }

    public function ofCheckoutReference(CheckoutReference $reference): ?CheckoutSession
    {
        return $this->entityManager->getRepository(CheckoutSession::class)->findOneBy(['checkoutReference' => $reference]);
    }
}
