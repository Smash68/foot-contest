<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Http;

use App\Organization\Application\InitiateOrganizationCheckout\InitiateOrganizationCheckoutCommand;
use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Infrastructure\Security\SecurityOrganizer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class InitiateOrganizationCheckoutController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/organizations/checkout', methods: ['POST'])]
    public function __invoke(
        #[MapRequestPayload] InitiateOrganizationCheckoutRequest $request,
        #[CurrentUser] SecurityOrganizer $organizer,
    ): JsonResponse {
        $envelope = $this->bus->dispatch(new InitiateOrganizationCheckoutCommand(
            $request->organizationName,
            $organizer->getUserIdentifier(),
        ));

        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        $reference = $handledStamp->getResult();
        assert($reference instanceof CheckoutReference);

        return new JsonResponse(['checkoutReference' => $reference->value], 201);
    }
}
