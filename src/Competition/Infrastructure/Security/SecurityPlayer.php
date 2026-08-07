<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Security;

use App\Competition\Domain\Model\PlayerId;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class SecurityPlayer implements UserInterface
{
    public function __construct(private PlayerId $playerId)
    {
    }

    public function getRoles(): array
    {
        return [];
    }

    public function getUserIdentifier(): string
    {
        $identifier = $this->playerId->value;
        assert($identifier !== '');

        return $identifier;
    }
}
