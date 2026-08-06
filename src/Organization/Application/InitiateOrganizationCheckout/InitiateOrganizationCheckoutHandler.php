<?php

declare(strict_types=1);

namespace App\Organization\Application\InitiateOrganizationCheckout;

use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Domain\Model\CheckoutSession;
use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Domain\Repository\CheckoutSessionRepository;
use App\Organization\Domain\Service\PaymentGateway;

final readonly class InitiateOrganizationCheckoutHandler
{
    public function __construct(
        private CheckoutSessionRepository $sessions,
        private PaymentGateway $paymentGateway,
    ) {
    }

    public function __invoke(InitiateOrganizationCheckoutCommand $command): CheckoutReference
    {
        $reference = $this->paymentGateway->initiateCheckout();

        $session = CheckoutSession::initiate(
            $this->sessions->nextIdentity(),
            $command->organizationName,
            new OrganizerId($command->ownerId),
            $reference,
        );

        $this->sessions->save($session);

        return $reference;
    }
}
