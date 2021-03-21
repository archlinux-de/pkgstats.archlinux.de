<?php

namespace App\ParamConverter;

use App\Request\PkgstatsRequestException;
use App\Request\StatisticsRangeRequest;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StatisticsRangeParamConverter implements ParamConverterInterface
{
    /** @var ValidatorInterface */
    private $validator;

    /**
     * @param ValidatorInterface $validator
     */
    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    /**
     * @param Request $request
     * @param ParamConverter $configuration
     * @return bool
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $defaultMonth = (int)date(
            'Ym',
            (int)strtotime(
                date(
                    '1-m-Y',
                    (int)strtotime('now -1 months')
                )
            )
        );

        $statisticsRangeRequest = new StatisticsRangeRequest(
            (int)$request->get('startMonth', $defaultMonth),
            (int)$request->get('endMonth', $defaultMonth)
        );

        $errors = $this->validator->validate($statisticsRangeRequest);
        if ($errors->count() > 0) {
            throw new PkgstatsRequestException($errors);
        }

        $request->attributes->set(
            $configuration->getName(),
            $statisticsRangeRequest
        );

        return true;
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
