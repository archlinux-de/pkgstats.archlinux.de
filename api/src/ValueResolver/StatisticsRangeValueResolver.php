<?php

namespace App\ValueResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Entity\Month;
use App\Request\PkgstatsRequestException;
use App\Request\StatisticsRangeRequest;

readonly class StatisticsRangeValueResolver implements ValueResolverInterface
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$argument->getType() || !is_a($argument->getType(), StatisticsRangeRequest::class, true)) {
            return [];
        }

        $defaultMonth = Month::create()->getYearMonth();

        $statisticsRangeRequest = new StatisticsRangeRequest(
            $request->query->getInt('startMonth', $defaultMonth),
            $request->query->getInt('endMonth', $defaultMonth) ?: $defaultMonth
        );

        $errors = $this->validator->validate($statisticsRangeRequest);
        if ($errors->count() > 0) {
            throw new PkgstatsRequestException($errors);
        }

        return [$statisticsRangeRequest];
    }
}
