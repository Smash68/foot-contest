<?php

declare(strict_types=1);

namespace App\Organization\Application\ConfirmOrganizationCheckout;

use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Domain\Model\CheckoutSessionStatus;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Repository\CheckoutSessionRepository;
use App\Organization\Domain\Repository\OrganizationRepository;

final readonly class ConfirmOrganizationCheckoutHandler
{
    public function __construct(
        private CheckoutSessionRepository $sessions,
        private OrganizationRepository $organizations,
    ) {
    }

    public function __invoke(ConfirmOrganizationCheckoutCommand $command): ?OrganizationId
    {
        $session = $this->sessions->ofCheckoutReference(new CheckoutReference($command->checkoutReference));

        if ($session === null) {
            throw new \InvalidArgumentException("Checkout session '{$command->checkoutReference}' does not exist.");
        }

        if ($session->getStatus() === CheckoutSessionStatus::Completed) {
            return $session->getOrganizationId();
        }

        if ($session->getStatus() === CheckoutSessionStatus::Failed) {
            return null;
        }

        if (!$command->succeeded) {
            $session->fail();
            $this->sessions->save($session);

            return null;
        }

        $organizationId = $this->organizations->nextIdentity();
        $organization = Organization::create($organizationId, $session->getOrganizationName(), $session->getOwnerId());
        $this->organizations->save($organization);

        $session->complete($organizationId);
        $this->sessions->save($session);

        return $organizationId;
    }
}
