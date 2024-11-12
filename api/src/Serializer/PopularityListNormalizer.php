<?php

namespace App\Serializer;

use App\Entity\CountryPopularityList;
use App\Entity\MirrorPopularityList;
use App\Entity\PackagePopularityList;
use App\Entity\PopularityListInterface;
use App\Entity\SystemArchitecturePopularityList;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class PopularityListNormalizer implements NormalizerInterface
{
    private NormalizerInterface $normalizer;

    public function __construct(
        #[Autowire(service: 'serializer.normalizer.object')] NormalizerInterface $normalizer,
    ) {
        assert($normalizer instanceof ObjectNormalizer);
        $this->normalizer = $normalizer;
    }

    /**
     * @return array<mixed>
     */
    public function normalize(mixed $object, ?string $format = null, array $context = []): array
    {
        assert(
            $object instanceof PopularityListInterface
            && ($object instanceof MirrorPopularityList
                || $object instanceof PackagePopularityList
                || $object instanceof SystemArchitecturePopularityList
                || $object instanceof CountryPopularityList
            )
        );

        $data = $this->normalizer->normalize(
            $object,
            $format,
            array_merge(
                $context,
                [
                    AbstractNormalizer::ATTRIBUTES => [
                        'total',
                        'count',
                        'limit',
                        'offset',
                        'query',
                        'mirrorPopularities',
                        'systemArchitecturePopularities',
                        'packagePopularities',
                        'countryPopularities'
                    ]
                ]
            )
        );

        assert(is_array($data));

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return ($data instanceof PopularityListInterface
                && ($data instanceof MirrorPopularityList
                    || $data instanceof PackagePopularityList
                    || $data instanceof SystemArchitecturePopularityList
                    || $data instanceof CountryPopularityList
                )
                            ) && $format === 'json';
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            PopularityListInterface::class => $format === 'json',
            MirrorPopularityList::class => $format === 'json',
            PackagePopularityList::class => $format === 'json',
            SystemArchitecturePopularityList::class => $format === 'json',
            CountryPopularityList::class => $format === 'json'
        ];
    }
}
