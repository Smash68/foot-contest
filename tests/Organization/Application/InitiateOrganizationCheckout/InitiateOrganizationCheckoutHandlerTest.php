<?php

declare(strict_types=1);

namespace App\Tests\Organization\Application\InitiateOrganizationCheckout;

use App\Organization\Application\InitiateOrganizationCheckout\InitiateOrganizationCheckoutCommand;
use App\Organization\Application\InitiateOrganizationCheckout\InitiateOrganizationCheckoutHandler;
use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Domain\Model\CheckoutSessionStatus;
use App\Organization\Infrastructure\Payment\FakePaymentGateway;
use App\Organization\Infrastructure\Persistence\InMemory\InMemoryCheckoutSessionRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InitiateOrganizationCheckoutHandlerTest extends TestCase
{
    #[Test]
    public function it_initiates_a_pending_checkout_session(): void
    {
        $sessions = new InMemoryCheckoutSessionRepository();
        $handler = new InitiateOrganizationCheckoutHandler($sessions, new FakePaymentGateway());

        $reference = $handler(new InitiateOrganizationCheckoutCommand('Ligue amateur du 92', '11111111-1111-1111-1111-111111111111'));

        self::assertInstanceOf(CheckoutReference::class, $reference);

        $session = $sessions->ofCheckoutReference($reference);
        self::assertNotNull($session);
        self::assertSame('Ligue amateur du 92', $session->getOrganizationName());
        self::assertSame(CheckoutSessionStatus::Pending, $session->getStatus());
    }
}
