<?php

namespace App\Request;

use Symfony\Component\String\ByteString;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[Assert\Callback('validate')]
class StatisticsRangeRequest
{
    private readonly int $startMonth;

    private readonly int $endMonth;

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

    private function isValidYearMonth(int $yearMonth): bool
    {
        $matches = new ByteString((string)$yearMonth)->match('/^([0-9]{4})([0-9]{2})$/');
        if (!$matches) {
            return false;
        }

        $yearMonth = (int)$matches[0]; // @phpstan-ignore cast.int
        $year = (int)$matches[1]; // @phpstan-ignore cast.int
        $month = (int)$matches[2]; // @phpstan-ignore cast.int

        return
            $year >= 2002 &&
            $month >= 1 && $month <= 12 &&
            $yearMonth <= (int)date('Ym');
    }

    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->startMonth !== 0 && !$this->isValidYearMonth($this->startMonth)) {
            $context->buildViolation('Invalid date')
                ->atPath('startMonth')
                ->addViolation();
        }

        if (!$this->isValidYearMonth($this->endMonth)) {
            $context->buildViolation('Invalid date')
                ->atPath('endMonth')
                ->addViolation();
        }
    }
}
