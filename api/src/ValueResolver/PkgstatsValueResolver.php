<?php

namespace App\ValueResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Request\PkgstatsRequest;
use App\Request\PkgstatsRequestException;

class PkgstatsValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly SerializerInterface $serializer
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$argument->getType() || !is_a($argument->getType(), PkgstatsRequest::class, true)) {
            return [];
        }

        $pkgstatsRequest = $this->serializer->deserialize(
            $request->getContent(),
            PkgstatsRequest::class,
            'json',
            ['clientIp' => $request->getClientIp()]
        );

        $errors = $this->validator->validate($pkgstatsRequest);
        if ($errors->count() > 0) {
            throw new PkgstatsRequestException($errors);
        }

        return [$pkgstatsRequest];
    }
}
