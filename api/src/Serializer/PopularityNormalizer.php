<?php

namespace App\Serializer;

use App\Entity\CountryPopularity;
use App\Entity\MirrorPopularity;
use App\Entity\PackagePopularity;
use App\Entity\PopularityInterface;
use App\Entity\SystemArchitecturePopularity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;

class PopularityNormalizer implements NormalizerInterface
{
    private readonly NormalizerInterface $normalizer;

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
            $object instanceof PopularityInterface
            && (
                $object instanceof MirrorPopularity
                || $object instanceof PackagePopularity
                || $object instanceof SystemArchitecturePopularity
                || $object instanceof CountryPopularity
            )
        );

        $data = $this->normalizer->normalize(
            $object,
            $format,
            array_merge(
                $context,
                [
                    AbstractNormalizer::ATTRIBUTES => [
                        'name',
                        'url',
                        'code',
                        'samples',
                        'count',
                        'popularity',
                        'startMonth',
                        'endMonth'
                    ]
                ]
            )
        );

        assert(is_array($data));

        return $data;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return (
            $data instanceof PopularityInterface
            && (
                $data instanceof MirrorPopularity
                || $data instanceof PackagePopularity
                || $data instanceof SystemArchitecturePopularity
                || $data instanceof CountryPopularity
            )
            && $format === 'json');
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            PopularityInterface::class => $format === 'json',
            MirrorPopularity::class => $format === 'json',
            PackagePopularity::class => $format === 'json',
            SystemArchitecturePopularity::class => $format === 'json',
            CountryPopularity::class => $format === 'json'
        ];
    }
}
