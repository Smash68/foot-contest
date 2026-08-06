<?php

declare(strict_types=1);

namespace App\Tests\Organization\Infrastructure\Http;

use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Domain\Model\CheckoutSession;
use App\Organization\Domain\Model\OrganizerId;
use App\Organization\Domain\Repository\CheckoutSessionRepository;
use App\Organization\Infrastructure\Persistence\InMemory\InMemoryCheckoutSessionRepository;
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

        $checkoutReference = new CheckoutReference('checkout_ref_789');
        $session = CheckoutSession::initiate(
            $sessions->nextIdentity(),
            'Ligue amateur du Nord',
            new OrganizerId('11111111-1111-1111-1111-111111111111'),
            $checkoutReference,
        );
        $sessions->save($session);

        $client->request('POST', '/organizations/checkout-webhook', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'checkoutReference' => 'checkout_ref_789',
            'succeeded' => true,
        ]));

        self::assertResponseStatusCodeSame(200);

        $payload = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('organizationId', $payload);
        self::assertNotNull($payload['organizationId']);
    }
}
