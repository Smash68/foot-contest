<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Domain\Exception\IncompleteTeamsException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Messenger\Exception\HandlerFailedException;

/**
 * Runs before LogicExceptionListener, which would otherwise answer this \LogicException without the incomplete teams:
 * setting the response stops the event propagation.
 */
final class IncompleteTeamsExceptionListener
{
    #[AsEventListener(event: KernelEvents::EXCEPTION, priority: 10)]
    public function __invoke(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();

        if ($throwable instanceof HandlerFailedException) {
            $throwable = $throwable->getPrevious() ?? $throwable;
        }

        if (!$throwable instanceof IncompleteTeamsException) {
            return;
        }

        $incompleteTeams = [];
        foreach ($throwable->teamNamesById as $id => $name) {
            $incompleteTeams[] = ['id' => $id, 'name' => $name];
        }

        $event->setResponse(new JsonResponse([
            'error' => $throwable->getMessage(),
            'incompleteTeams' => $incompleteTeams,
        ], 409));
    }
}
