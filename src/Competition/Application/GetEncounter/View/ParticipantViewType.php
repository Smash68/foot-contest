<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter\View;

enum ParticipantViewType: string
{
    case Team = 'team';
    case Bye = 'bye';
    case Pending = 'pending';
}
