<?php

namespace App\ParamConverter;

use App\Request\StatisticsRangeRequest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;

class StatisticsRangeParamConverter implements ParamConverterInterface
{
    /** @var int */
    private $rangeMonths;

    /**
     * @param int $rangeMonths
     */
    public function __construct(int $rangeMonths)
    {
        $this->rangeMonths = $rangeMonths;
    }

    /**
     * @param Request $request
     * @param ParamConverter $configuration
     * @return bool
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $defaultStartMonth = $this->formatYearMonth(
            (int)strtotime(date(
                '1-m-Y',
                (int)strtotime('now -' . $this->rangeMonths . ' months')
            ))
        );

        $defaultEndMonth = $this->formatYearMonth(
            (int)strtotime(date('1-m-Y'))
        );

        $statisticsRangeRequest = new StatisticsRangeRequest(
            $request->get('startMonth', $defaultStartMonth),
            $request->get('endMonth', $defaultEndMonth)
        );

        $request->attributes->set(
            $configuration->getName(),
            $statisticsRangeRequest
        );

        return true;
    }

    /**
     * @param int $time
     * @return string
     */
    private function formatYearMonth(int $time): string
    {
        return date('Ym', $time);
    }

    /**
     * @param ParamConverter $configuration
     * @return bool
     */
    public function supports(ParamConverter $configuration)
    {
        return $configuration->getClass() == StatisticsRangeRequest::class;
    }
}
