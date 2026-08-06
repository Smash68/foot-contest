<?php

declare(strict_types=1);

namespace App\Tests\Organization\Domain\Model;

use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Domain\Model\CheckoutSession;
use App\Organization\Domain\Model\CheckoutSessionId;
use App\Organization\Domain\Model\CheckoutSessionStatus;
use App\Organization\Domain\Model\OrganizationId;
use App\Organization\Domain\Model\OrganizerId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckoutSessionTest extends TestCase
{
    #[Test]
    public function it_is_initiated_as_pending(): void
    {
        $id = new CheckoutSessionId('33333333-3333-3333-3333-333333333333');
        $reference = new CheckoutReference('cs_test_123');
        $ownerId = new OrganizerId('11111111-1111-1111-1111-111111111111');

        $session = CheckoutSession::initiate($id, 'Ligue amateur du 92', $ownerId, $reference);

        self::assertTrue($id->equals($session->getId()));
        self::assertSame('Ligue amateur du 92', $session->getOrganizationName());
        self::assertTrue($ownerId->equals($session->getOwnerId()));
        self::assertTrue($reference->equals($session->getCheckoutReference()));
        self::assertSame(CheckoutSessionStatus::Pending, $session->getStatus());
    }

    #[Test]
    public function it_completes_with_the_created_organization_id(): void
    {
        $session = self::pendingSession();
        $organizationId = new OrganizationId('22222222-2222-2222-2222-222222222222');

        $session->complete($organizationId);

        self::assertSame(CheckoutSessionStatus::Completed, $session->getStatus());
        self::assertTrue($organizationId->equals($session->getOrganizationId()));
    }

    #[Test]
    public function it_cannot_complete_an_already_completed_session(): void
    {
        $session = self::pendingSession();
        $session->complete(new OrganizationId('22222222-2222-2222-2222-222222222222'));

        $this->expectException(\LogicException::class);

        $session->complete(new OrganizationId('44444444-4444-4444-4444-444444444444'));
    }

    #[Test]
    public function it_fails(): void
    {
        $session = self::pendingSession();

        $session->fail();

        self::assertSame(CheckoutSessionStatus::Failed, $session->getStatus());
    }

    private static function pendingSession(): CheckoutSession
    {
        return CheckoutSession::initiate(
            new CheckoutSessionId('33333333-3333-3333-3333-333333333333'),
            'Ligue amateur du 92',
            new OrganizerId('11111111-1111-1111-1111-111111111111'),
            new CheckoutReference('cs_test_123'),
        );
    }
}
