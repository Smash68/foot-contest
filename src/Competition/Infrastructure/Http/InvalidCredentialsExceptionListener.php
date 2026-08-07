<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Domain\Exception\InvalidCredentialsException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

final class InvalidCredentialsExceptionListener
{
    #[AsEventListener(event: KernelEvents::EXCEPTION)]
    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if ($throwable instanceof HandlerFailedException) {
            $throwable = $throwable->getPrevious() ?? $throwable;
        }

        if (!$throwable instanceof InvalidCredentialsException) {
            return;
        }

        $event->setResponse(new JsonResponse(['error' => $throwable->getMessage()], 401));
    }
}
