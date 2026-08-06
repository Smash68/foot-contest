<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Http;

use App\Organization\Application\ConfirmOrganizationCheckout\ConfirmOrganizationCheckoutCommand;
use App\Organization\Domain\Model\OrganizationId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class ConfirmOrganizationCheckoutController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/organizations/checkout-webhook', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] ConfirmOrganizationCheckoutRequest $request): JsonResponse
    {
        $envelope = $this->bus->dispatch(new ConfirmOrganizationCheckoutCommand(
            $request->checkoutReference,
            $request->succeeded,
        ));

        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        $organizationId = $handledStamp->getResult();
        assert($organizationId === null || $organizationId instanceof OrganizationId);

        return new JsonResponse(['organizationId' => $organizationId?->value], 200);
    }
}
