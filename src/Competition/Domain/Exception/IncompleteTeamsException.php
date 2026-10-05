<?php

declare(strict_types=1);

namespace App\Competition\Domain\Exception;

final class IncompleteTeamsException extends \LogicException
{
    /**
     * @param array<string, string> $teamNamesById the teams below the minimum roster size, names keyed by team id
     */
    public function __construct(
        string $competitionId,
        public readonly array $teamNamesById,
    ) {
        parent::__construct("Competition '{$competitionId}' has teams below its minimum roster size: ".implode(', ', $teamNamesById).'.');
    }
}
