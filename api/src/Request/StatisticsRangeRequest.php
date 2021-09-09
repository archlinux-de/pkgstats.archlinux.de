<?php

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

class StatisticsRangeRequest
{
    /**
     * @Assert\AtLeastOneOf({
     *   @Assert\EqualTo(0),
     *   @Assert\DateTime("Ym")
     * })
     */
    private int $startMonth;

    /**
     * @Assert\DateTime("Ym")
     */
    private int $endMonth;

    public function __construct(int $startMonth, int $endMonth)
    {
        $this->startMonth = $startMonth;
        $this->endMonth = $endMonth;
    }

    public function getStartMonth(): int
    {
        return $this->startMonth;
    }

    public function getEndMonth(): int
    {
        return $this->endMonth;
    }
}
