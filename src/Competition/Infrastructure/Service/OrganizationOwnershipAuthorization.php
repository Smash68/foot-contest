<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Service;

use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Domain\Service\OrganizerOrganizationAuthorization;
use App\Organization\Application\IsOrganizerOwnerOfOrganization\IsOrganizerOwnerOfOrganizationQuery;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final readonly class OrganizationOwnershipAuthorization implements OrganizerOrganizationAuthorization
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function authorizes(string $organizerId, OrganizationId $organizationId): bool
    {
        $envelope = $this->bus->dispatch(new IsOrganizerOwnerOfOrganizationQuery($organizerId, $organizationId->value));

        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        $result = $handledStamp->getResult();
        assert(is_bool($result));

        return $result;
    }
}
