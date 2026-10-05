<?php

declare(strict_types=1);

namespace App\Tests\Support\Http;

use App\Organization\Domain\Repository\OrganizationRepository;
use App\Organization\Domain\Repository\OrganizerRepository;
use App\Organization\Domain\Service\AccessTokenIssuer;
use App\Tests\Support\Builder\OrganizationBuilder;
use App\Tests\Support\Builder\OrganizerBuilder;
use Psr\Container\ContainerInterface;

/**
 * An organizer signed in for an HTTP test: unlike a builder, signing in has side effects —
 * the organizer and the organization they own are saved, and an access token is issued.
 */
final readonly class AuthenticatedOrganizer
{
    private function __construct(
        public string $token,
        public string $organizationId,
    ) {
    }

    public static function signIn(ContainerInterface $container): self
    {
        $organizers = $container->get(OrganizerRepository::class);
        assert($organizers instanceof OrganizerRepository);
        $organizerId = $organizers->nextIdentity();
        $organizers->save(OrganizerBuilder::anOrganizer()->withId($organizerId->value)->build());

        $organizations = $container->get(OrganizationRepository::class);
        assert($organizations instanceof OrganizationRepository);
        $organizationId = $organizations->nextIdentity();
        $organizations->save(OrganizationBuilder::anOrganization()
            ->withId($organizationId->value)
            ->ownedBy($organizerId->value)
            ->build());

        $accessTokenIssuer = $container->get(AccessTokenIssuer::class);
        assert($accessTokenIssuer instanceof AccessTokenIssuer);

        return new self($accessTokenIssuer->issue($organizerId), $organizationId->value);
    }

    /**
     * @return array{HTTP_AUTHORIZATION: string}
     */
    public function authorizationHeader(): array
    {
        return ['HTTP_AUTHORIZATION' => "Bearer {$this->token}"];
    }
}
