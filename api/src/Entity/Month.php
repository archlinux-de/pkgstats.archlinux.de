<?php

namespace App\Entity;

use DateTimeImmutable;
use DateTimeInterface;

class Month
{
    private static ?int $baseTimeStamp = null;

    public function __construct(private readonly int $timestamp = 0)
    {
    }

    public static function resetBaseTimestamp(): ?int
    {
        return self::setBaseTimestamp(null);
    }

    public static function setBaseTimestamp(?int $timestamp): ?int
    {
        $oldBaseTimestamp = self::$baseTimeStamp;
        self::$baseTimeStamp = $timestamp;
        return $oldBaseTimestamp;
    }

    public static function create(int $relative = -1): self
    {
        return new self(
            (int)strtotime(
                sprintf('first day of this month %d months', $relative),
                self::$baseTimeStamp
            )
        );
    }

    public function getYearMonth(): int
    {
        return (int)$this->getDateTime()->format('Ym');
    }

    public function getDateTime(): DateTimeInterface
    {
        return (new DateTimeImmutable())->setTimestamp($this->getTimestamp());
    }

    public function getTimestamp(): int
    {
        return $this->timestamp;
    }
}
