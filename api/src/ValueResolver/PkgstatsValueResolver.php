<?php

namespace App\ValueResolver;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use App\Request\PkgstatsRequest;
use App\Request\PkgstatsRequestException;

readonly class PkgstatsValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ValidatorInterface $validator,
        private SerializerInterface $serializer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return iterable<PkgstatsRequest>
     */
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
            $this->logger->error($errors, ['request' => $request->getContent(), 'clientIp' => $request->getClientIp()]);
            throw new PkgstatsRequestException($errors);
        }

        return [$pkgstatsRequest];
    }
}
