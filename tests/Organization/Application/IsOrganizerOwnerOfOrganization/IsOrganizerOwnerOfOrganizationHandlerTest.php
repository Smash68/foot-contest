<?php

declare(strict_types=1);

namespace App\Tests\Organization\Application\IsOrganizerOwnerOfOrganization;

use App\Organization\Application\IsOrganizerOwnerOfOrganization\IsOrganizerOwnerOfOrganizationHandler;
use App\Organization\Application\IsOrganizerOwnerOfOrganization\IsOrganizerOwnerOfOrganizationQuery;
use App\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizationRepository;
use App\Tests\Support\Builder\OrganizationBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IsOrganizerOwnerOfOrganizationHandlerTest extends TestCase
{
    #[Test]
    public function it_confirms_ownership_when_the_organizer_owns_the_organization(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $organization = OrganizationBuilder::anOrganization()->ownedBy('organizer-1')->build();
        $organizations->save($organization);
        $handler = new IsOrganizerOwnerOfOrganizationHandler($organizations);

        $result = $handler(new IsOrganizerOwnerOfOrganizationQuery('organizer-1', $organization->getId()->value));

        self::assertTrue($result);
    }

    #[Test]
    public function it_denies_ownership_when_the_organization_belongs_to_another_organizer(): void
    {
        $organizations = new InMemoryOrganizationRepository();
        $organization = OrganizationBuilder::anOrganization()->ownedBy('organizer-1')->build();
        $organizations->save($organization);
        $handler = new IsOrganizerOwnerOfOrganizationHandler($organizations);

        $result = $handler(new IsOrganizerOwnerOfOrganizationQuery('organizer-2', $organization->getId()->value));

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