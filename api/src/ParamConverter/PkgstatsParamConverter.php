<?php

namespace App\ParamConverter;

use App\Request\PkgstatsRequest;
use App\Request\PkgstatsRequestException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class PkgstatsParamConverter implements ParamConverterInterface
{
    /** @var ValidatorInterface */
    private $validator;

    /** @var SerializerInterface */
    private $serializer;

    /** @var DenormalizerInterface */
    private $denormalizer;

    /**
     * @param ValidatorInterface $validator
     * @param SerializerInterface $serializer
     * @param DenormalizerInterface $denormalizer
     */
    public function __construct(
        ValidatorInterface $validator,
        SerializerInterface $serializer,
        DenormalizerInterface $denormalizer
    ) {
        $this->validator = $validator;
        $this->serializer = $serializer;
        $this->denormalizer = $denormalizer;
    }

    /**
     * @param Request $request
     * @param ParamConverter $configuration
     * @return bool
     */
    public function apply(Request $request, ParamConverter $configuration)
    {
        $context = [
            'clientIp' => $request->getClientIp(),
            'userAgent' => $request->server->get('HTTP_USER_AGENT')
        ];
        if ($request->headers->get('Content-Type') === 'application/json') {
            $pkgstatsRequest = $this->serializer->deserialize(
                $request->getContent(),
                PkgstatsRequest::class,
                'json',
                $context
            );
        } else {
            $pkgstatsRequest = $this->denormalizer->denormalize(
                $request->request->all(),
                PkgstatsRequest::class,
                'form',
                $context
            );
        }

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

    /**
     * @param ParamConverter $configuration
     * @return bool
     */
    public function supports(ParamConverter $configuration)
    {
        return $configuration->getClass() == PkgstatsRequest::class;
    }
}
