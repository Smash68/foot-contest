<?php

declare(strict_types=1);

namespace App\Competition\Domain\Service;

use App\Competition\Domain\Model\PlayerId;

interface AccessTokenIssuer
{
    public function issue(PlayerId $playerId): string;
}
