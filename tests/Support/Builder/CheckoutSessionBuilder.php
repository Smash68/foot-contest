<?php

declare(strict_types=1);

namespace App\Tests\Support\Builder;

use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Domain\Model\CheckoutSession;
use App\Organization\Domain\Model\CheckoutSessionId;
use App\Organization\Domain\Model\OrganizerId;

final class CheckoutSessionBuilder
{
    private string $id = 'checkout-session-1';
    private string $organizationName = 'Ligue amateur du 92';
    private string $ownerId = 'organizer-1';
    private string $checkoutReference = 'cs_test_123';

    public static function aCheckoutSession(): self
    {
        return new self();
    }

    /** The checkout reference returned by the payment gateway; pass one when the test looks the session up by it. */
    public function withCheckoutReference(string $checkoutReference): self
    {
        $clone = clone $this;
        $clone->checkoutReference = $checkoutReference;

        return $clone;
    }

    public function build(): CheckoutSession
    {
        return CheckoutSession::initiate(
            new CheckoutSessionId($this->id),
            $this->organizationName,
            new OrganizerId($this->ownerId),
            new CheckoutReference($this->checkoutReference),
        );
    }
}
