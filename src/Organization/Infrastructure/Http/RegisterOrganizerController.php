<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Http;

use App\Organization\Application\RegisterOrganizer\RegisterOrganizerCommand;
use App\Organization\Domain\Model\OrganizerId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterOrganizerController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/organizers', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] RegisterOrganizerRequest $request): JsonResponse
    {
        $envelope = $this->bus->dispatch(new RegisterOrganizerCommand(
            $request->email,
            $request->password,
        ));

        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        $id = $handledStamp->getResult();
        assert($id instanceof OrganizerId);

        return new JsonResponse(['id' => $id->value], 201);
    }
}
