<?php

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

class StatisticsRangeRequest
{
    /**
     * @var int
     * @Assert\AtLeastOneOf({
     *   @Assert\EqualTo(0),
     *   @Assert\DateTime("Ym")
     * })
     */
    private $startMonth;

    /**
     * @var int
     * @Assert\DateTime("Ym")
     */
    private $endMonth;

    /**
     * @param int $startMonth
     * @param int $endMonth
     */
    public function __construct(int $startMonth, int $endMonth)
    {
        $this->startMonth = $startMonth;
        $this->endMonth = $endMonth;
    }

    /**
     * @return int
     */
    public function getStartMonth(): int
    {
        return $this->startMonth;
    }

    /**
     * @return int
     */
    public function getEndMonth(): int
    {
        return $this->endMonth;
    }
}
