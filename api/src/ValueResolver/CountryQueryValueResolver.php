<?php

namespace App\ValueResolver;

use App\Request\CountryQueryRequest;
use App\Request\PkgstatsRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class CountryQueryValueResolver implements ValueResolverInterface
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    /**
     * @return iterable<CountryQueryRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$argument->getType() || !is_a($argument->getType(), CountryQueryRequest::class, true)) {
            return [];
        }

        $query = $request->get('query', '');
        if (!is_string($query)) {
            throw new BadRequestHttpException('Invalid request');
        }

        $countryQueryRequest = new CountryQueryRequest($query);

        $errors = $this->validator->validate($countryQueryRequest);
        if ($errors->count() > 0) {
            throw new PkgstatsRequestException($errors);
        }

        return [$countryQueryRequest];
    }
}
