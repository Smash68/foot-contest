<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final readonly class EncounterResult
{
    private function __construct(
        public Score $regularTime,
        public ?Score $extraTime = null,
        public ?Score $penalties = null,
    ) {
    }

    public static function of(Score $regularTime, ?Score $extraTime = null, ?Score $penalties = null): self
    {
        return self::regularTime($regularTime);
    }

    public static function regularTime(Score $score): self
    {
        return new self($score);
    }

    public static function afterExtraTime(Score $regularTime, Score $extraTime): self
    {
        if (!$regularTime->isDraw()) {
            throw new \InvalidArgumentException('Extra time requires a draw in regular time.');
        }

        if ($extraTime->isDraw()) {
            throw new \InvalidArgumentException('A draw after extra time requires a penalty shootout.');
        }

        if ($extraTime->home < $regularTime->home || $extraTime->away < $regularTime->away) {
            throw new \InvalidArgumentException('Extra time score cannot be lower than regular time score.');
        }

        return new self($regularTime, $extraTime);
    }

    public static function afterPenalties(Score $regularTime, ?Score $extraTime, Score $penalties): self
    {
        if (!$regularTime->isDraw()) {
            throw new \InvalidArgumentException('Penalties require a draw in regular time.');
        }

        if ($extraTime !== null) {
            if (!$extraTime->isDraw()) {
                throw new \InvalidArgumentException('Penalties require a draw after extra time.');
            }

            if ($extraTime->home < $regularTime->home || $extraTime->away < $regularTime->away) {
                throw new \InvalidArgumentException('Extra time score cannot be lower than regular time score.');
            }
        }

        if ($penalties->isDraw()) {
            throw new \InvalidArgumentException('A draw in a penalty shootout is not possible.');
        }

        return new self($regularTime, $extraTime, $penalties);
    }

    public function winner(): Side
    {
        if ($this->penalties !== null) {
            return $this->penalties->home > $this->penalties->away ? Side::Home : Side::Away;
        }

        if ($this->extraTime !== null) {
            return $this->extraTime->home > $this->extraTime->away ? Side::Home : Side::Away;
        }

        if ($this->isDraw()) {
            throw new \LogicException('Cannot determine a winner from a draw.');
        }

        return $this->regularTime->home > $this->regularTime->away ? Side::Home : Side::Away;
    }

    public function isDraw(): bool
    {
        return $this->penalties === null && $this->extraTime === null && $this->regularTime->isDraw();
    }

    public function wentStraightToPenalties(): bool
    {
        return $this->penalties !== null && $this->extraTime === null;
    }
}
