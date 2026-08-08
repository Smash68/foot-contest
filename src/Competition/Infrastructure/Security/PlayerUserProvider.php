<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Security;

use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Repository\PlayerRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @implements UserProviderInterface<SecurityPlayer>
 */
final readonly class PlayerUserProvider implements UserProviderInterface
{
    public function __construct(private PlayerRepository $players)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $player = $this->players->ofId(new PlayerId($identifier));

        if ($player === null) {
            throw new UserNotFoundException("Player '{$identifier}' not found.");
        }

        return new SecurityPlayer($player->getId());
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof SecurityPlayer) {
            throw new UnsupportedUserException($user::class.' is not supported.');
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return SecurityPlayer::class === $class;
    }
}
