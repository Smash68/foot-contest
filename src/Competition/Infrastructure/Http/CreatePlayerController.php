<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\CreatePlayer\CreatePlayerCommand;
use App\Competition\Domain\Model\PlayerId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class CreatePlayerController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/players', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CreatePlayerRequest $request): JsonResponse
    {
        $envelope = $this->bus->dispatch(new CreatePlayerCommand(
            $request->name,
            $request->email,
        ));

        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        $id = $handledStamp->getResult();
        assert($id instanceof PlayerId);

        return new JsonResponse(['id' => $id->value], 201);
    }
}
