<?php

declare(strict_types=1);

namespace App\Tests\Competition\Infrastructure\Service;

use App\Competition\Domain\Model\OrganizationId;
use App\Competition\Infrastructure\Service\OrganizationOwnershipAuthorization;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Domain\Repository\OrganizationRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class OrganizationOwnershipAuthorizationTest extends KernelTestCase
{
    #[Test]
    public function it_authorizes_the_owner_of_the_organization(): void
    {
        $container = self::getContainer();
        $organizations = $container->get(OrganizationRepository::class);
        assert($organizations instanceof OrganizationRepository);
        $organizationId = $organizations->nextIdentity();
        $organizations->save(Organization::create($organizationId, 'Ligue amateur du Nord', new OrganizerId('organizer-1')));

        $bus = $container->get(MessageBusInterface::class);
        assert($bus instanceof MessageBusInterface);
        $authorization = new OrganizationOwnershipAuthorization($bus);

        $result = $authorization->authorizes('organizer-1', new OrganizationId($organizationId->value));

        self::assertTrue($result);
    }

    #[Test]
    public function it_rejects_an_organizer_who_does_not_own_the_organization(): void
    {
        $container = self::getContainer();
        $organizations = $container->get(OrganizationRepository::class);
        assert($organizations instanceof OrganizationRepository);
        $organizationId = $organizations->nextIdentity();
        $organizations->save(Organization::create($organizationId, 'Ligue amateur du Nord', new OrganizerId('organizer-1')));

        $bus = $container->get(MessageBusInterface::class);
        assert($bus instanceof MessageBusInterface);
        $authorization = new OrganizationOwnershipAuthorization($bus);

        $result = $authorization->authorizes('organizer-2', new OrganizationId($organizationId->value));

        self::assertFalse($result);
    }
}
