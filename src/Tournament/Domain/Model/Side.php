<?php

declare(strict_types=1);

namespace App\Tournament\Domain\Model;

enum Side
{
    case Home;
    case Away;
}