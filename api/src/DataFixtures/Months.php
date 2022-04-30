<?php

namespace App\DataFixtures;

use Traversable;

class Months implements \IteratorAggregate
{
    /**
     * @return Traversable<int>
     */
    public function getIterator(): Traversable
    {
        for ($i = 0; $i < 50; $i++) {
            yield (int)(new \DateTimeImmutable(sprintf('first day of this month -%d month', $i)))->format('Ym');
        }
    }
}
