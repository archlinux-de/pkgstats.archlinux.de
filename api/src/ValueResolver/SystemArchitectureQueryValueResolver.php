<?php

namespace App\ValueResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Request\SystemArchitectureQueryRequest;
use App\Request\PkgstatsRequestException;

class SystemArchitectureQueryValueResolver implements ValueResolverInterface
{
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$argument->getType() || !is_a($argument->getType(), SystemArchitectureQueryRequest::class, true)) {
            return [];
        }

        $query = $request->get('query', '');
        if (!is_string($query)) {
            throw new BadRequestHttpException('Invalid request');
        }

        $systemArchitectureQueryRequest = new SystemArchitectureQueryRequest($query);

        $errors = $this->validator->validate($systemArchitectureQueryRequest);
        if ($errors->count() > 0) {
            throw new PkgstatsRequestException($errors);
        }

        return [$systemArchitectureQueryRequest];
    }
}
