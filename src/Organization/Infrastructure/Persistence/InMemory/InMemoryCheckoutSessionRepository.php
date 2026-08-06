<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Persistence\InMemory;

use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Domain\Model\CheckoutSession;
use App\Organization\Domain\Model\CheckoutSessionId;
use App\Organization\Domain\Repository\CheckoutSessionRepository;
use Symfony\Component\Uid\Uuid;

final class InMemoryCheckoutSessionRepository implements CheckoutSessionRepository
{
    /** @var array<string, CheckoutSession> */
    private array $sessions = [];

    public function nextIdentity(): CheckoutSessionId
    {
        return new CheckoutSessionId(Uuid::v7()->toRfc4122());
    }

    public function save(CheckoutSession $session): void
    {
        $this->sessions[$session->getId()->value] = $session;
    }

    public function ofCheckoutReference(CheckoutReference $reference): ?CheckoutSession
    {
        foreach ($this->sessions as $session) {
            if ($session->getCheckoutReference()->equals($reference)) {
                return $session;
            }
        }

        return null;
    }
}
