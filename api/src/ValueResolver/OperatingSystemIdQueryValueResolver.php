<?php

namespace App\ValueResolver;

use App\Request\OperatingSystemIdQueryRequest;
use App\Request\PkgstatsRequestException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class OperatingSystemIdQueryValueResolver implements ValueResolverInterface
{
    public function __construct(private ValidatorInterface $validator)
    {
    }

    /**
     * @return iterable<OperatingSystemIdQueryRequest>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$argument->getType() || !is_a($argument->getType(), OperatingSystemIdQueryRequest::class, true)) {
            return [];
        }

        $query = $request->query->get('query', '');
        if (!is_string($query)) {
            throw new BadRequestHttpException('Invalid request');
        }

        $operatingSystemIdQueryRequest = new OperatingSystemIdQueryRequest($query);

        $errors = $this->validator->validate($operatingSystemIdQueryRequest);
        if ($errors->count() > 0) {
            throw new PkgstatsRequestException($errors);
        }

        return [$operatingSystemIdQueryRequest];
    }
}
