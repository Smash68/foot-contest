<?php

declare(strict_types=1);

namespace App\Organization\Domain\Service;

use App\Organization\Domain\Model\OrganizerId;

interface AccessTokenIssuer
{
    public function issue(OrganizerId $organizerId): string;
}
