<?php

namespace App\ParamConverter;

use App\Request\PkgstatsRequest;
use App\Request\PkgstatsRequestException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PkgstatsParamConverter implements ParamConverterInterface
{
    public function __construct(private ValidatorInterface $validator, private SerializerInterface $serializer)
    {
    }

    public function apply(Request $request, ParamConverter $configuration): bool
    {
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

        $request->attributes->set(
            $configuration->getName(),
            $pkgstatsRequest
        );

        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return $configuration->getClass() == PkgstatsRequest::class;
    }
}
