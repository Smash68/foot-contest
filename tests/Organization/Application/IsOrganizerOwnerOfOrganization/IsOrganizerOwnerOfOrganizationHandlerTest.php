<?php

declare(strict_types=1);

namespace App\Tests\Organization\Application\IsOrganizerOwnerOfOrganization;

use App\Organization\Application\IsOrganizerOwnerOfOrganization\IsOrganizerOwnerOfOrganizationHandler;
use App\Organization\Application\IsOrganizerOwnerOfOrganization\IsOrganizerOwnerOfOrganizationQuery;
use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IsOrganizerOwnerOfOrganizationHandlerTest extends TestCase
{
    #[Test]
    public function it_confirms_ownership_when_the_organizer_owns_the_organization(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $organizationId = $organizations->nextIdentity();
        $organizations->save(Organization::create($organizationId, 'Ligue amateur du Nord', new OrganizerId('organizer-1')));
        $handler = new IsOrganizerOwnerOfOrganizationHandler($organizations);

        $result = $handler(new IsOrganizerOwnerOfOrganizationQuery('organizer-1', $organizationId->value));

        self::assertTrue($result);
    }

    #[Test]
    public function it_denies_ownership_when_the_organization_belongs_to_another_organizer(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $organizationId = $organizations->nextIdentity();
        $organizations->save(Organization::create($organizationId, 'Ligue amateur du Nord', new OrganizerId('organizer-1')));
        $handler = new IsOrganizerOwnerOfOrganizationHandler($organizations);

        $result = $handler(new IsOrganizerOwnerOfOrganizationQuery('organizer-2', $organizationId->value));

        self::assertFalse($result);
    }

    #[Test]
    public function it_denies_ownership_when_the_organization_does_not_exist(): void
    {
        $handler = new IsOrganizerOwnerOfOrganizationHandler(new InMemoryOrganizationRepository());

        $result = $handler(new IsOrganizerOwnerOfOrganizationQuery('organizer-1', 'unknown-org'));

        self::assertFalse($result);
    }
}
