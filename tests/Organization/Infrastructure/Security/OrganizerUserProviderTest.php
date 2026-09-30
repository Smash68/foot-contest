<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Security;

use App\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizerRepository;
use App\Organization\Infrastructure\Security\OrganizerUserProvider;
use App\Tests\Support\Builder\OrganizerBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class OrganizerUserProviderTest extends TestCase
{
    #[Test]
    public function it_loads_an_organizer_by_its_identifier(): void
    {
        $organizer = OrganizerBuilder::anOrganizer()->build();
        $organizers = new InMemoryOrganizerRepository();
        $organizers->save($organizer);
        $provider = new OrganizerUserProvider($organizers);

        $user = $provider->loadUserByIdentifier($organizer->getId()->value);

        self::assertSame($organizer->getId()->value, $user->getUserIdentifier());
    }

    #[Test]
    public function it_throws_when_the_organizer_does_not_exist(): void
    {
        $provider = new OrganizerUserProvider(new InMemoryOrganizerRepository());

        $this->expectException(UserNotFoundException::class);

        $provider->loadUserByIdentifier('11111111-1111-1111-1111-111111111111');
    }
}
