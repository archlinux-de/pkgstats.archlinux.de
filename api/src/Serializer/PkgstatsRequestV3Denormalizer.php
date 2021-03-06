<?php

namespace App\Serializer;

use App\Entity\Country;
use App\Entity\Mirror;
use App\Entity\OperatingSystemArchitecture;
use App\Entity\Package;
use App\Entity\SystemArchitecture;
use App\Request\PkgstatsRequest;
use App\Service\GeoIp;
use App\Service\MirrorUrlFilter;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class PkgstatsRequestV3Denormalizer implements DenormalizerInterface, CacheableSupportsMethodInterface
{
    /** @var GeoIp */
    private $geoIp;

    /** @var MirrorUrlFilter */
    private $mirrorUrlFilter;

    /**
     * @param GeoIp $geoIp
     * @param MirrorUrlFilter $mirrorUrlFilter
     */
    public function __construct(GeoIp $geoIp, MirrorUrlFilter $mirrorUrlFilter)
    {
        $this->geoIp = $geoIp;
        $this->mirrorUrlFilter = $mirrorUrlFilter;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }

    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $packages = $this->filterList($data['pacman']['packages'] ?? []);
        $arch = ($data['os']['architecture'] ?? '');
        $cpuArch = $data['system']['architecture'] ?? '';
        $mirror = $this->mirrorUrlFilter->filter(($data['pacman']['mirror'] ?? ''));

        $clientIp = $context['clientIp'] ?? '127.0.0.1';

        $pkgstatsver = $data['version'] ?? '';
        $pkgstatsRequest = new PkgstatsRequest($pkgstatsver);
        $pkgstatsRequest->setOperatingSystemArchitecture(
            (new OperatingSystemArchitecture($arch))->setMonth((int)date('Ym'))
        );
        $pkgstatsRequest->setSystemArchitecture((new SystemArchitecture($cpuArch))->setMonth((int)date('Ym')));

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

    public function supportsDenormalization($data, string $type, string $format = null)
    {
        return $type === PkgstatsRequest::class && $format === 'json';
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
