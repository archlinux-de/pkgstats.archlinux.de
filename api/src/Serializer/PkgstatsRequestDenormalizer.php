<?php

namespace App\Serializer;

use App\Entity\Country;
use App\Entity\Mirror;
use App\Entity\OperatingSystemArchitecture;
use App\Entity\Package;
use App\Entity\SystemArchitecture;
use App\Request\PkgstatsRequest;
use App\Service\GeoIpInterface;
use App\Service\MirrorUrlFilter;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

readonly class PkgstatsRequestDenormalizer implements DenormalizerInterface
{
    public function __construct(private GeoIpInterface $geoIp, private MirrorUrlFilter $mirrorUrlFilter)
    {
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            PkgstatsRequest::class => true
        ];
    }

    public function denormalize(mixed $data, string $type, string $format = null, array $context = []): PkgstatsRequest
    {
        assert(is_array($data));
        assert(is_string($context['clientIp']) || is_null($context['clientIp']));

        $packages = $this->filterList($data['pacman']['packages'] ?? []); // @phpstan-ignore-line
        $arch = ($data['os']['architecture'] ?? '');  // @phpstan-ignore-line
        $cpuArch = $data['system']['architecture'] ?? '';  // @phpstan-ignore-line
        $mirror = $this->mirrorUrlFilter->filter(($data['pacman']['mirror'] ?? '')); // @phpstan-ignore-line

        $clientIp = $context['clientIp'] ?? '127.0.0.1';

        $pkgstatsRequest = new PkgstatsRequest($data['version'] ?? ''); // @phpstan-ignore-line
        $pkgstatsRequest->setOperatingSystemArchitecture(
            (new OperatingSystemArchitecture($arch))->setMonth((int)date('Ym')) // @phpstan-ignore-line
        );
        $pkgstatsRequest->setSystemArchitecture(
            (new SystemArchitecture($cpuArch))->setMonth((int)date('Ym')) // @phpstan-ignore-line
        );

        if ($mirror) {
            $pkgstatsRequest->setMirror((new Mirror($mirror))->setMonth((int)date('Ym')));
        }

        $countryCode = $this->geoIp->getCountryCode($clientIp);
        if ($countryCode) {
            $pkgstatsRequest->setCountry((new Country($countryCode))->setMonth((int)date('Ym')));
        }

        foreach ($packages as $package) {
            $pkgstatsRequest->addPackage(
                (new Package())
                    ->setName($package)
                    ->setMonth((int)date('Ym'))
            );
        }

        return $pkgstatsRequest;
    }

    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === PkgstatsRequest::class;
    }

    /**
     * @param string[] $array
     * @return string[]
     */
    private function filterList(array $array): array
    {
        return array_filter(array_unique($array));
    }
}
