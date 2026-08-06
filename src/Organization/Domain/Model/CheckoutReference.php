<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

final readonly class CheckoutReference
{
    public function __construct(public string $value)
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException('CheckoutReference cannot be empty.');
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
