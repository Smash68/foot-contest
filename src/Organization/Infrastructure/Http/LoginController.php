<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Http;

use App\Organization\Application\Login\LoginCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class LoginController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/login', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] LoginRequest $request): JsonResponse
    {
        $envelope = $this->bus->dispatch(new LoginCommand(
            $request->email,
            $request->password,
        ));

        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        $token = $handledStamp->getResult();
        assert(is_string($token));

        return new JsonResponse(['token' => $token], 200);
    }
}
