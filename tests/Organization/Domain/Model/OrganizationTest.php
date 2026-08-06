<?php

declare(strict_types=1);

namespace App\Tests\Organization\Domain\Model;

use App\Organization\Domain\Model\Organization;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Model\OrganizerId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OrganizationTest extends TestCase
{
    #[Test]
    public function it_is_created_with_an_id_a_name_and_an_owner(): void
    {
        $id = new OrganizationId('22222222-2222-2222-2222-222222222222');
        $ownerId = new OrganizerId('11111111-1111-1111-1111-111111111111');

        $organization = Organization::create($id, 'Ligue amateur du 92', $ownerId);

        self::assertTrue($id->equals($organization->getId()));
        self::assertSame('Ligue amateur du 92', $organization->getName());
        self::assertTrue($ownerId->equals($organization->getOwnerId()));
    }
}
