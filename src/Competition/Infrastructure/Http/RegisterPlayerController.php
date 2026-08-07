<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\RegisterPlayer\RegisterPlayerCommand;
use App\Competition\Domain\Model\PlayerId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class RegisterPlayerController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/players', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] RegisterPlayerRequest $request): JsonResponse
    {
        $envelope = $this->bus->dispatch(new RegisterPlayerCommand(
            $request->name,
            $request->email,
            $request->password,
        ));

        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        $id = $handledStamp->getResult();
        assert($id instanceof PlayerId);

        return new JsonResponse(['id' => $id->value], 201);
    }
}
