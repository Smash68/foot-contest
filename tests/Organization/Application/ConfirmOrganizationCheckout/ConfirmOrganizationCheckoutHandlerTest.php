<?php

declare(strict_types=1);

namespace App\Tests\Organization\Application\ConfirmOrganizationCheckout;

use App\Organization\Application\ConfirmOrganizationCheckout\ConfirmOrganizationCheckoutCommand;
use App\Organization\Application\ConfirmOrganizationCheckout\ConfirmOrganizationCheckoutHandler;
use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Domain\Model\CheckoutSession;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Infrastructure\Persistence\InMemory\InMemoryCheckoutSessionRepository;
use App\Organization\Infrastructure\Persistence\InMemory\InMemoryOrganizationRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfirmOrganizationCheckoutHandlerTest extends TestCase
{
    #[Test]
    public function it_creates_the_organization_when_the_payment_succeeded(): void
    {
        $sessions = new InMemoryCheckoutSessionRepository();
        $session = CheckoutSession::initiate(
            $sessions->nextIdentity(),
            'Ligue amateur du 92',
            new OrganizerId('11111111-1111-1111-1111-111111111111'),
            new CheckoutReference('cs_test_123'),
        );
        $sessions->save($session);

        $organizations = new InMemoryOrganizationRepository();
        $handler = new ConfirmOrganizationCheckoutHandler($sessions, $organizations);

        $organizationId = $handler(new ConfirmOrganizationCheckoutCommand('cs_test_123', true));

        self::assertInstanceOf(OrganizationId::class, $organizationId);

        $organization = $organizations->ofId($organizationId);
        self::assertNotNull($organization);
        self::assertSame('Ligue amateur du 92', $organization->getName());
    }

    #[Test]
    public function it_does_not_create_the_organization_when_the_payment_failed(): void
    {
        $sessions = new InMemoryCheckoutSessionRepository();
        $session = CheckoutSession::initiate(
            $sessions->nextIdentity(),
            'Ligue amateur du 92',
            new OrganizerId('11111111-1111-1111-1111-111111111111'),
            new CheckoutReference('cs_test_123'),
        );
        $sessions->save($session);

        $organizations = new InMemoryOrganizationRepository();
        $handler = new ConfirmOrganizationCheckoutHandler($sessions, $organizations);

        $organizationId = $handler(new ConfirmOrganizationCheckoutCommand('cs_test_123', false));

        self::assertNull($organizationId);
    }

    #[Test]
    public function it_is_idempotent_when_called_twice_for_the_same_successful_session(): void
    {
        $sessions = new InMemoryCheckoutSessionRepository();
        $session = CheckoutSession::initiate(
            $sessions->nextIdentity(),
            'Ligue amateur du 92',
            new OrganizerId('11111111-1111-1111-1111-111111111111'),
            new CheckoutReference('cs_test_123'),
        );
        $sessions->save($session);

        $organizations = new InMemoryOrganizationRepository();
        $handler = new ConfirmOrganizationCheckoutHandler($sessions, $organizations);

        $first = $handler(new ConfirmOrganizationCheckoutCommand('cs_test_123', true));
        $second = $handler(new ConfirmOrganizationCheckoutCommand('cs_test_123', true));

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertTrue($first->equals($second));
    }
}
