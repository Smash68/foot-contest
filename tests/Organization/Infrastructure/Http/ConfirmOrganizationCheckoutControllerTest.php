<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Http;

use App\Organization\Domain\Repository\CheckoutSessionRepository;
use App\Organization\Infrastructure\Persistence\InMemory\InMemoryCheckoutSessionRepository;
use App\Tests\Support\Builder\CheckoutSessionBuilder;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ConfirmOrganizationCheckoutControllerTest extends WebTestCase
{
    #[Test]
    public function it_confirms_a_successful_checkout_and_creates_the_organization(): void
    {
        $client = static::createClient();

        $sessions = new InMemoryCheckoutSessionRepository();
        self::getContainer()->set(CheckoutSessionRepository::class, $sessions);

        $sessions->save(CheckoutSessionBuilder::aCheckoutSession()->withCheckoutReference('checkout_ref_789')->build());

        $client->request('POST', '/organizations/checkout-webhook', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'checkoutReference' => 'checkout_ref_789',
            'succeeded' => true,
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($payload);
        self::assertArrayHasKey('organizationId', $payload);
        self::assertNotNull($payload['organizationId']);
    }
}
