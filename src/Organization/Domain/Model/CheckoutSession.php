<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

final class CheckoutSession
{
    private function __construct(
        private readonly CheckoutSessionId $id,
        private readonly string $organizationName,
        private readonly OrganizerId $ownerId,
        private readonly CheckoutReference $checkoutReference,
        private CheckoutSessionStatus $status,
        private ?OrganizationId $organizationId = null,
    ) {
    }

    public static function initiate(
        CheckoutSessionId $id,
        string $organizationName,
        OrganizerId $ownerId,
        CheckoutReference $checkoutReference,
    ): self {
        return new self($id, $organizationName, $ownerId, $checkoutReference, CheckoutSessionStatus::Pending);
    }

    public function complete(OrganizationId $organizationId): void
    {
        $this->ensurePending();

        $this->status = CheckoutSessionStatus::Completed;
        $this->organizationId = $organizationId;
    }

    public function fail(): void
    {
        $this->ensurePending();

        $this->status = CheckoutSessionStatus::Failed;
    }

    private function ensurePending(): void
    {
        if ($this->status !== CheckoutSessionStatus::Pending) {
            throw new \LogicException("Checkout session '{$this->id->value}' is not pending.");
        }
    }

    public function getOrganizationId(): OrganizationId
    {
        if ($this->organizationId === null) {
            throw new \LogicException('Checkout session has no organization id yet.');
        }

        return $this->organizationId;
    }

    public function getId(): CheckoutSessionId
    {
        return $this->id;
    }

    public function getOrganizationName(): string
    {
        return $this->organizationName;
    }

    public function getOwnerId(): OrganizerId
    {
        return $this->ownerId;
    }

    public function getCheckoutReference(): CheckoutReference
    {
        return $this->checkoutReference;
    }

    public function getStatus(): CheckoutSessionStatus
    {
        return $this->status;
    }
}
