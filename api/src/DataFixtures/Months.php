<?php

namespace App\DataFixtures;

use App\Entity\Month;
use IteratorAggregate;
use Traversable;

class Months implements IteratorAggregate
{
    private const DEFAULT_NUMBER_OF_MONTHS = 50;

    private static int $numberOfNonths = self::DEFAULT_NUMBER_OF_MONTHS;

    public static function resetNumberOfMonths(): int
    {
        return self::setNumberOfMonths(self::DEFAULT_NUMBER_OF_MONTHS);
    }

    public static function setNumberOfMonths(int $amount): int
    {
        $oldNumberOfNonths = self::$numberOfNonths;
        self::$numberOfNonths = $amount;
        return $oldNumberOfNonths;
    }

    /**
     * @return Traversable<int>
     */
    public function getIterator(): Traversable
    {
        for ($i = 0; $i < self::$numberOfNonths; $i++) {
            yield Month::create(-1 * $i)->getYearMonth();
        }
    }
}
